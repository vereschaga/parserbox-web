<?php

namespace AwardWallet\Engine\supersaver\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirm extends \TAccountChecker
{
    public $mailFiles = "supersaver/it-11912367.eml, supersaver/it-12117101.eml, supersaver/it-12126210.eml, supersaver/it-155425816.eml, supersaver/it-155978953.eml, supersaver/it-159550184.eml, supersaver/it-25972277.eml, supersaver/it-2762955.eml, supersaver/it-32400603.eml, supersaver/it-34393762.eml, supersaver/it-34864266.eml, supersaver/it-35748544.eml, supersaver/it-3696579.eml, supersaver/it-3801751.eml, supersaver/it-3802150.eml, supersaver/it-48681175.eml, supersaver/it-6046578.eml, supersaver/it-6065710.eml, supersaver/it-6388829.eml, supersaver/it-6411136.eml, supersaver/it-6455363.eml, supersaver/it-6545015.eml, supersaver/it-6567252.eml, supersaver/it-6593922.eml, supersaver/it-70151358.eml, supersaver/it-8235957.eml, supersaver/it-82480944.eml, supersaver/it-8336646.eml, supersaver/it-8354904.eml, supersaver/it-83609561.eml, supersaver/it-8476810.eml, supersaver/it-8499977.eml, supersaver/it-86744078.eml, supersaver/it-8752524.eml, supersaver/it-8836326.eml";

    public static $detectFrom = [
        'gotogate' => [
            'gotogate.',
            'Gotogate',
            '\'Gotogate\'', // not error
        ],
        'flybillet' => [
            'flybillet.',
            '\'Flybillet\'',
        ],
        'trip' => [
            '@support.br.mytrip.com',
            '@mytrip.',
            '@Mytrip.',
            '@trip.',
            '@avion.',
            '@airtickets24.',
            'pamediakopes.gr',
            '\'Mytrip\'', // not error
            '\'Pamediakopes\'', // not error
        ],
        'fnt' => [
            '.flightnetwork.com',
            '\'Flight Network\'',
        ],
        'supersaver' => [
            'supersaver.',
            'travelpartner.',
            'travelstart.',
            'seat24.',
            'Seat24.',
            'etraveli.com',
            'flygresor.',
            'charter.',
            'flygvaruhuset.',
            'goleif.',
            'budjet.',
            'Supersavertravel',
        ],
    ];

    public $detectBody = [
        'da'     => ['Tak, fordi du har booket din rejse hos os!', 'Bookingnummer'],
        'nl'     => ['Hartelijk dank voor je boeking!', 'Boekingsreferentie'],
        'nl2'    => ['Hartelijk dank voor je boeking!', 'Boekingsnummer'],
        'fi'     => ['kiittää tilauksestasi!', 'Varausnumero'],
        'fr'     => ["Merci d'avoir réservé votre voyage", 'Réf. de la réservation'],
        'no'     => ["Takk for at du har bestilt reisen hos oss!", 'Bookingnummer'],
        'de'     => ["Vielen Dank, dass Sie Ihre Reise bei uns gebucht haben!", 'Buchungsnr'],
        'de2'    => ["Bitte beachten Sie! Sie reisen mit einem ELEKTRONISCHEN TICKET", 'Buchungsnr'],
        'en'     => ["Thank you for your order!", 'Booking No.'],
        'pt'     => ["Obrigado pelo seu pedido!", 'Referência de reserva'],
        'pt2'    => ["Agradecemos seu pedido!", 'Nº da reserva'],
        'es'     => ["Muchas gracias por tu compra!", 'Localizador'],
        'es2'    => ['Gracias por su pedido', 'Nro. de reserva'],
        'hu'     => ["Köszönjük a rendelését!", 'Foglalási hivatkozás'],
        'it'     => ["Grazie per la prenotazione", 'Codice di prenotazione'],
        'ru'     => ["Важная информация о Вашей поездке", 'Номер брони'],
        'sv'     => ["Viktig information om din resa", "Bokningsnummer"],
        'sv2'    => ["Här kommer viktig information om din bokning ", "Bokningsnummer"],
        'sv3'    => ['Tack för att du har bokat din resa hos oss', 'Bokningsnummer'],
        'pl'     => ["Ważne informacje dotyczące Twojej podróży", "Numer rezerwacji"],
        'pl2'    => ["Dziękujemy za złożenie zamówienia", "Numer rezerwacji"],
        'tr'     => ['Rezervasyonlarım seyahatiniz hakkında önemli bilgiler', 'Rezervasyon referansı'],
        'ro'     => ['Vă mulţumim pentru comandă', 'Referinţă rezervare'],
        'ko'     => ['이용해 주셔서 감사합니다', '예약 참조 정보'],
        'tr2'    => ['Siparişiniz için teşekkür ederiz!', 'Rezervasyon referansı'],
        'sk'     => ['Ďakujeme vám za objednávku!', 'Objednávateľ'],
        'cs'     => ['Děkujeme vám za Vaši objednávku', 'Číslo rezervace'],
        'ja'     => ['ありがとうございます', '申し込み番号'],
        'uk'     => ["Важлива інформація щодо вашої подорожі", 'Номер замовлення'],
        'el'     => ["Σημαντικές πληροφορίες για το ταξίδι σας", 'Αριθμός παραγγελίας'],
        'th'     => ["ขอขอบคุณที่จองการเดินทางกับเรา", 'หมายเลขสั่งซื้อ'],
    ];

    public static $dict = [
        'da' => [ // it-6388829.eml, it-6411136.eml, it-6545015.eml
            'Udrejse'  => ['Udrejse', 'Ankomst'],
            'Operator' => ['Flyves af', 'Opereres af'],
            //            'Flight @' => '', // to translate, @ = number
            //            'Siddeplads' => '',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'nl' => [ // it-6455363.eml, it-8499977.eml
            'Bookingnummer' => ['Boekingsreferentie', 'Boekingsnummer'],
            'Ordrenummer'   => ['Boekingsnummer', 'Ordernummer'],
            'Ordre dato'    => 'Boekingsdatum',
            'Afrejse'       => 'Aankomst',
            'Fra'           => 'Van',
            'Udrejse'       => 'Vertrek',
            'Hjemrejse'     => 'Terugvlucht:',
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => ['Voornamen paspoort', 'Voornamen'],
            'Efternavn' => 'Achternaam',
            'SUM'       => ['Eindtotaal', 'Totaal'],
            'Operator'  => 'Uitgevoerd door',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'fi' => [ // it-34864266.eml, it-6567252.eml, it-6593922.eml
            'Bookingnummer' => 'Varausnumero',
            'Ordrenummer'   => 'Tilausnumero',
            'Ordre dato'    => 'Tilauspäivä',
            'Afrejse'       => 'Saapuminen',
            'Fra'           => 'Mistä',
            'Udrejse'       => 'Meno',
            'Hjemrejse'     => 'Paluu',
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Etunimet',
            'Efternavn' => 'Sukunimi',
            'SUM'       => ['YHTEENSÄ', 'YHTEENSÄ*'],
            'Operator'  => 'Operoija',
            'Terminal'  => 'Terminaali',

            // Hotel
            "Hotel"       => "Hotel",
            "Room Type"   => "Room Type",
            "Voucher No." => "Voucher No.",
            "Guest Name"  => "Guest Name",
            "Saapuminen:" => "Saapuminen:",
            "Lähtö:"      => "Lähtö:",
        ],
        'fr' => [ // it-3696579.eml, it-6046578.eml, it-8752524.eml
            'Bookingnummer' => 'Réf. de la réservation',
            'Ordrenummer'   => 'Numéro de commande',
            'Ordre dato'    => 'Date de la commande',
            'Afrejse'       => 'Arrivée',
            'Fra'           => 'De',
            'Udrejse'       => ['Départ'],
            'Hjemrejse'     => 'Retour',
            //            'Flight @' => '', // @ = number
            'Siddeplads' => 'Siège',
            'Fornavn'    => 'Prénom',
            'Efternavn'  => 'Nom de famille',
            'SUM'        => ['Total', 'TOTAL'],
            'Operator'   => 'Vol assuré par',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'no' => [ // it-3802150.eml, it-8354904.eml
            'Bookingnummer' => 'Bookingnummer',
            'Ordrenummer'   => 'Ordrenummer',
            'Ordre dato'    => 'Bestillingsdato',
            'Afrejse'       => 'Ankomst',
            'Fra'           => 'Fra',
            'Udrejse'       => 'Utreise',
            'Hjemrejse'     => 'Hjemreise',
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Flight @'  => 'Reise @',
            'Fornavn'   => 'Fornavn',
            'Efternavn' => 'Etternavn',
            'SUM'       => 'SUM',
            'Operator'  => 'Opereres av',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'de' => [ // it-3801751.eml
            'Bookingnummer' => ['Buchungsnr', 'Buchungs-Nr.'],
            'Ordrenummer'   => ['Bestellnummer', 'Buchungsnummer'],
            'Ordre dato'    => 'Buchungsdatum',
            'Afrejse'       => 'Hinreise',
            'Fra'           => 'Von',
            'Udrejse'       => ['Hinflug', 'Hinreise ', 'Hinreise:'],
            'Hjemrejse'     => ['Rückflug', 'Rückreise ', 'Rückreise:'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Vorname',
            'Efternavn' => 'Nachname',
            'SUM'       => 'SUMME',
            //			'Operator' => '',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'en' => [
            'Bookingnummer' => 'Booking No.',
            'Ordrenummer'   => 'Order number',
            'Ordre dato'    => 'Order date',
            'Afrejse'       => 'Arrival',
            'Fra'           => 'From',
            'Udrejse'       => ['Departure'],
            'Hjemrejse'     => ['Return'],
            //            'Flight @' => '', // @ = number
            'Siddeplads' => 'Seat',
            'Fornavn'    => 'First name',
            'Efternavn'  => 'Last name',
            'SUM'        => ['SUM', 'PAID'],
            'Operator'   => 'Operated by',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'pt' => [ // it-6065710.eml
            'Bookingnummer' => ['Referência de reserva', 'Nº da reserva'],
            'Ordrenummer'   => ['Número de pedido', 'Número do pedido'],
            'Ordre dato'    => 'Data do pedido',
            'Afrejse'       => 'Partida',
            'Fra'           => 'De',
            'Udrejse'       => ['Partida'],
            'Hjemrejse'     => ['Ida e volta'],
            //            'Flight @' => '', // @ = number
            'Siddeplads' => ['Assento', 'Lugar'],
            'Fornavn'    => ['Nome próprio', 'Nome'],
            'Efternavn'  => 'Sobrenome',
            'SUM'        => ['SOMA', 'TOTAL'],
            'Operator'   => 'Operado por',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'es' => [ // it-32400603.eml, it-8235957.eml, it-8336646.eml
            'Bookingnummer' => ['Localizador', 'Nro. de reserva'],
            'Ordrenummer'   => ['Número de reserva', 'Número de pedido'],
            'Ordre dato'    => ['Fecha de pedido', 'Fecha del pedido'],
            'Afrejse'       => 'Llegada',
            'Fra'           => ['Origen', 'De'],
            'Udrejse'       => ['Salida'],
            'Hjemrejse'     => ['Vuelta'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Nombre',
            'Efternavn' => 'Apellidos',
            'SUM'       => ['Total', 'TOTAL'],
            'Operator'  => 'Operado por',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'hu' => [
            'Bookingnummer' => 'Foglalási hivatkozás',
            'Ordrenummer'   => 'Rendelési szám',
            'Ordre dato'    => 'Rendelési dátum',
            'Afrejse'       => 'Érkezés',
            'Fra'           => 'Indulási hely',
            'Udrejse'       => ['Indulás'],
            'Hjemrejse'     => ['Visszaindulás'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Keresztnév',
            'Efternavn' => 'Vezetéknév',
            'SUM'       => 'ÖSSZEG',
            //			'Operator' => '',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'it' => [ // it-8476810.eml
            'Bookingnummer' => 'Codice di prenotazione',
            'Ordrenummer'   => 'Numero ordine',
            'Ordre dato'    => 'Data ordine',
            'Afrejse'       => 'Arrivo',
            'Fra'           => 'Da',
            'Udrejse'       => ['Andata'],
            'Hjemrejse'     => ['Ritorno'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Nome',
            'Efternavn' => 'Cognome',
            'SUM'       => 'TOTALE',
            'Operator'  => 'Operato da',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'ru' => [
            'Bookingnummer' => 'Номер брони',
            'Ordrenummer'   => 'Номер заказа',
            'Ordre dato'    => 'Дата заказа',
            'Afrejse'       => 'Прибытие',
            'Fra'           => 'Из',
            'Udrejse'       => ['Отправление'],
            'Hjemrejse'     => ['Возвращение'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Имя латинскими буквами',
            'Efternavn' => 'Фамилия латинскими буквами',
            'SUM'       => 'СУММА',
            'Operator'  => 'Обслуживается авиакомпанией',
            'Terminal'  => 'Терминал',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'sv' => [ // it-35748544.eml
            'Bookingnummer' => 'Bokningsnummer',
            'Ordrenummer'   => 'Ordernummer',
            'Ordre dato'    => 'Orderdatum',
            'Afrejse'       => 'Ankomst',
            'Fra'           => 'Från',
            'Udrejse'       => ['Utresa'],
            'Hjemrejse'     => ['Hemresa'],
            'Flight @'      => 'Resa @', // @ = number
            'Siddeplads'    => 'Sittplats',
            'Fornavn'       => 'Förnamn',
            'Efternavn'     => 'Efternamn',
            'SUM'           => ['SUMMA', 'Prisspecifikation'],
            'Operator'      => 'Trafikeras av',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'pl' => [ // it-8836326.eml
            'Bookingnummer' => 'Numer rezerwacji',
            'Ordrenummer'   => 'Numer zamówienia',
            'Ordre dato'    => 'Data zamówienia',
            'Afrejse'       => 'Przylot',
            'Fra'           => 'Miejsce wylotu',
            'Udrejse'       => ['Wylot'],
            'Hjemrejse'     => 'Powrót',
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Imię',
            'Efternavn' => 'Nazwisko',
            'SUM'       => 'SUMA',
            'Operator'  => 'Lot wykonywany przez',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'tr' => [ // it-70151358.eml
            'Bookingnummer' => 'Rezervasyon referansı',
            'Ordrenummer'   => ['Sipariş numaras', 'Sipariş numarası'],
            'Ordre dato'    => 'Sipariş tarihi',
            'Afrejse'       => 'Kalkış',
            'Fra'           => 'Varış',
            'Udrejse'       => 'Gidiş',
            'Hjemrejse'     => ['Varış', 'Dönüş'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Ad',
            'Efternavn' => 'Soyad',
            'SUM'       => 'TOPLAM',
            'Operator'  => 'Havayolu',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'ro' => [ // it-25972277.eml
            'Bookingnummer' => 'Referinţă rezervare',
            'Ordrenummer'   => 'Număr comandă',
            'Ordre dato'    => 'Dată Comandă',
            'Afrejse'       => 'Sosire',
            'Fra'           => 'De la',
            'Udrejse'       => ['Plecare'],
            'Hjemrejse'     => ['Retur'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Prenume',
            'Efternavn' => 'Nume',
            'SUM'       => ['SUMĂ'],
            'Operator'  => 'Operat de',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'ko' => [ // it-48681175.eml
            'Bookingnummer' => '예약 참조 정보',
            'Ordrenummer'   => '주문 번호',
            'Ordre dato'    => '예약 일자',
            'Afrejse'       => '도착',
            'Fra'           => '출발',
            'Udrejse'       => '출발',
            'Hjemrejse'     => '돌아오는 편',
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => '이름(라틴 문자)',
            'Efternavn' => '성(라틴 문자)',
            //            'SUM' => '',
            'Operator' => '운영 항공사:',
            'Terminal' => '터미널',

            // Hotel
            //            'Hotel' => '',
            //            'Room Type' => '',
            //            'Voucher No.' => '',
            //            'Guest Name' => '',
            //            'Saapuminen:' => '',
            //            'Lähtö:' => '',
        ],
        'sk' => [
            'Bookingnummer' => 'Referenčný údaj rezerváci',
            'Ordrenummer'   => 'Číslo objednávky',
            'Ordre dato'    => 'Dátum objednávky',
            'Afrejse'       => 'Prílet',
            'Fra'           => 'Z',
            'Udrejse'       => ['Odlet:'],
            'Hjemrejse'     => ['Návrat:'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Krstné meno',
            'Efternavn' => 'Priezvisko',
            'SUM'       => 'SÚČET',
            'Operator'  => 'Prevádzkuje',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'cs' => [ // it-82480944.eml
            'Bookingnummer' => 'Číslo rezervace',
            'Ordrenummer'   => 'Číslo objednávky',
            'Ordre dato'    => 'Datum objednávky',
            'Afrejse'       => 'Do',
            'Fra'           => 'Od',
            'Udrejse'       => ['Odlet'],
            'Hjemrejse'     => ['Přílet'],
            //            'Flight @' => '', // @ = number
            'Siddeplads' => 'Seat',
            'Fornavn'    => 'Křestní jméno',
            'Efternavn'  => 'Prydybailo',
            'SUM'        => 'CELKEM',
            'Operator'   => 'Letecká společnost',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'ja' => [ // it-83609561.eml
            'Bookingnummer' => '予約番号',
            'Ordrenummer'   => '申し込み番号',
            'Ordre dato'    => '注文日',
            'Afrejse'       => '目的地',
            'Fra'           => '出発地',
            'Udrejse'       => ['出発'],
            'Hjemrejse'     => ['到着'],
            //            'Flight @' => '', // @ = number
            'Siddeplads' => ['Seat', '座席'],
            'Fornavn'    => 'ローマ字の名',
            'Efternavn'  => 'ローマ字の姓',
            'SUM'        => ['合計'],
            'Operator'   => '航空会社',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'uk' => [ // it-86744078.eml
            'Bookingnummer' => 'Номер бронювання:',
            'Ordrenummer'   => 'Номер замовлення',
            'Ordre dato'    => 'Дата замовлення',
            'Afrejse'       => 'Прибуття',
            'Fra'           => 'З',
            'Udrejse'       => ['Відправлення'],
            'Hjemrejse'     => ['Повернення:'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => 'Seat',
            'Fornavn'   => 'Ім’я латинськими буквами',
            'Efternavn' => 'Прізвище латинськими буквами',
            'SUM'       => 'СУМА',
            'Operator'  => 'Літак, який здійснює рейси:',
            'Terminal'  => 'Термінал',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
        'el' => [
            'Bookingnummer' => 'Κωδικός κράτησης',
            'Ordrenummer'   => 'Αριθμός παραγγελίας',
            'Ordre dato'    => 'Ημερομηνία παραγγελίας',
            'Afrejse'       => 'Άφιξη',
            'Fra'           => 'Από',
            'Udrejse'       => ['Αναχώρηση'],
            'Hjemrejse'     => ['Επιστροφή'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'Μικρό όνομα με λατινικούς χαρακτήρες',
            'Efternavn' => 'Επίθετο με λατινικούς χαρακτήρες',
            'SUM'       => 'ΕΞΟΦΛΗΘΗΚΕ',
            //            'Operator'  => 'Πετάξτε με',
            'Terminal'  => 'Αεροσταθμός (Terminal)',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],

        'th' => [
            'Bookingnummer' => 'ข้อมูลอ้างอิงการจอง:',
            'Ordrenummer'   => 'หมายเลขสั่งซื้อ',
            'Ordre dato'    => 'วันที่สั่งซื้อ',
            'Afrejse'       => 'ไปยัง',
            'Fra'           => 'จาก',
            'Udrejse'       => ['การออกเดินทาง'],
            'Hjemrejse'     => ['การมาถึง'],
            //            'Flight @' => '', // @ = number
            //            'Siddeplads' => '',
            'Fornavn'   => 'ชื่อเป็นตัวอักษรละติน',
            'Efternavn' => 'นามสกุลเป็นตัวอักษรละติน',
            'SUM'       => 'รวม',
            //            'Operator'  => 'Πετάξτε με',
            //            'Terminal'  => '',

            // Hotel
            //            "Hotel" => "",
            //            "Room Type" => "",
            //            "Voucher No." => "",
            //            "Guest Name" => "",
            //            "Saapuminen:" => "",
            //            "Lähtö:" => "",
        ],
    ];

    private $detectSubject = [
        //da
        '#Bekræftelse Ordrenummer: [A-Z\d]+, Bookingnummer: Fly: [A-Z\d]+#',
        //nl
        '#Bevestiging; Boekingsnummer: [A-Z\d]+, Boekingsreferentie: Vlucht: [A-Z\d]+#',

        '#Supersaver.dk - din booking & kvittering., Ordrenummer: [A-Z\d]+, Bookingnummer: Fly: [A-Z\d]+#',
        '#Bekreftelse; Ordrenummer: [A-Z\d]+, Bookingnummer: Fly: [A-Z\d]+#',
        "de" => '#Bestätigung Bestellnummer: [A-Z\d]+, Buchungsnr.: Flug: [A-Z\d]+#',
        // fi
        "fi" => '#Vahvistus: Tilausnumero: [A-Z\d]+, Varausnumero: Lento: [A-Z\d]+#',
        '#Supersaver.fi, varaus ja kuitti., Tilausnumero: [A-Z\d]+, Varausnumero: Lento: [A-Z\d]+#',
        "fr" => '#Confirmation : Numéro de commande: [A-Z\d]+, Référence de réservation: Vol: [A-Z\d]+#',
        "en" => '#Confirmation; Order number: [A-Z\d]+, Booking references: Flight: [A-Z\d]+#',
        "pt" => '#Confirmação;? Número d[eo] pedido: [A-Z\d]+, Referências d[ea] reserva: Voo: [A-Z\d]+#',
        "es" => '#Confirmación; Número de reserva: [A-Z\d]+, Localizador: Vuelo: [A-Z\d]+#',
        "hu" => '#Visszaigazolás; Rendelési szám: [A-Z\d]+, Foglalási hivatkozások: Járat: [A-Z\d]+#',
        "it" => '#Conferma; Numero ordine: [A-Z\d]+, FRiferimenti prenotazione: Volo: [A-Z\d]+#',
        "ru" => '#Подтверждение; Номер заказа: [A-Z\d]+, Номер брони::? Авиарейс: [A-Z\d]+#',
        "sv" => '#(?:Bekräftelse;|Här kommer viktig information om din bokning,) Ordernummer: [A-Z\d]+, Bokningsnummer: Flyg: [A-Z\d]+#',
        "pl" => '#Potwierdzenie Numer zamówienia: [A-Z\d]+#',
        "ro" => '#Confirmare; Număr comandă: [A-Z\d]+, Referinţe rezervare: Zbor: [A-Z\d]+#',
        'ko' => '#확인정보 주문 번호: [A-Z\d]+, 예약 참조 정보: 항공편: [A-Z\d]+#u',
        'tr' => '#Onay; Sipariş numarası: [A-Z\d]+, Rezervasyon referansları: [A-Z\d]+#',
        'sk' => '#Ďakujeme za rezerváciu! Potvrdenie\. Číslo objednávky: [A-Z\d]+, Referenčné údaje rezervácie: Let: [A-Z\d]+#u',
        'cs' => '#Potvrzení\; Číslo objednávky\: [A-Z\d]+\, Čísla rezervací\: Let\: [A-Z\d]+#',
        'ja' => '#申し込み番号\: [A-Z\d]+\, 予約番号\: フライト: [A-Z\d]+#',
        "uk" => '#Дякуємо за бронювання! Підтвердження Номер замовлення: [A-Z\d]+, Номер бронювання: Рейс: [A-Z\d]+, Рейс: [A-Z\d]+#',
        // el
        '/Ευχαριστούμε για την κράτησή σας! Επιβεβαίωση: Αριθμός παραγγελίας: [A-Z\d]+, Κωδικοί κράτησης: Πτήση: [A-Z\d]+/',
    ];

    private $lang = '';
    private $date;
    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider();
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        // Travel Agency
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordrenummer'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, '/^[A-Z\d]{5,}$/');
        $tripNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordrenummer'))} and ancestor::td[1]/following-sibling::td[1]]", null, true, '/^(.+?)[\s:：]*$/u');

        if (empty($tripNumber)) {
            $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordrenummer'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
            $tripNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordrenummer'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        }
        $email->ota()->confirmation($tripNumber, $tripNumberTitle);

        // Price
        $total = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t('SUM')) . "][ancestor::td[1]/following-sibling::td])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]"));

        if (empty($total['Total'])) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t('SUM')) . "]/following::tr[normalize-space()][1]/descendant::td[2]"));
        }

        if ($total['Total'] !== null) {
            $email->price()->total($total['Total']);
        }

        if (!empty($total['Currency'])) {
            $email->price()->currency($total['Currency']);
        }

        $this->flight($email);
        $this->hotel($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectFrom as $emailFrom) {
            $emailFromName = array_map(function ($v) {
                return trim($v, '.@');
            }, $emailFrom);
            $xpath = "//img[" . $this->contains($emailFromName, '@src') . "]
			| //img[" . $this->contains($emailFromName, '@alt') . " or " . $this->contains($emailFromName, '@altx') . "]
			| //link[" . $this->contains($emailFromName, '@href') . "]
			| //a[" . $this->contains(str_replace('@', '.', $emailFrom), '@href') . "] 
			| //em[" . $this->contains(str_replace('@', '.', $emailFrom)) . "]";

            if ($this->http->XPath->query($xpath)->length > 0
                // example: it-48681175.eml not detected through xpath
                || $this->arrikey($parser->getHTMLBody(), $emailFrom) !== false) {
                if ($this->AssignLang()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $find = false;

        foreach (self::$detectFrom as $code => $emailFroms) {
            foreach ($emailFroms as $emailFrom) {
                if (stripos($headers["from"], $emailFrom) !== false) {
                    $find = true;
                    $this->providerCode = $code;

                    break 2;
                }
            }
        }

        if ($find == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (preg_match($dSubject, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectFrom as $emailFroms) {
            foreach ($emailFroms as $emailFrom) {
                if (stripos($from, $emailFrom) !== false) {
                    return true;
                }
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
        return array_keys(self::$detectFrom);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            if ($this->lang == 'th') {
                $monthNameOriginal = $this->re("/(\D+)/", $date);
            } else {
                $monthNameOriginal = $m[0];
            }

            if (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordre dato'))}]/following::text()[normalize-space(.)!=''][1]"))));

        $node = $this->http->XPath->query("//text()[{$this->contains($this->t('Fornavn'))}]/ancestor::tr[1][{$this->contains($this->t('Efternavn'))}]/following-sibling::tr[count(.//td)>4]");

        if ($node->length == 0) {
            $node = $this->http->XPath->query("//text()[{$this->starts($this->t('Fornavn'))}]/ancestor::tr[1][{$this->contains($this->t('Efternavn'))} or contains(., 'Surname')]/following-sibling::tr[count(.//td)>4]");
        }

        foreach ($node as $ps) {
            $Passengers[] = $this->http->FindSingleNode("./td[1]", $ps) . ' ' . $this->http->FindSingleNode("./td[2]", $ps);
        }

        if (isset($Passengers)) {
            $f->general()
                ->travellers(array_values(array_unique($Passengers)), true);
        }

        $xpath = "//text()[normalize-space(.)='{$this->t('Afrejse')}']/ancestor::tr[1][{$this->contains($this->t('Fra'))}]/following-sibling::tr[count(.//td)>4]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments didnt found by xpath: {$xpath}");

            return [];
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $ruleXpath = $this->contains($this->t('Udrejse')) . " or " . $this->contains($this->t('Hjemrejse')) . ' or ' . $this->starts($this->t("Flight @"), 'translate(normalize-space(), "0123456789", "@@@@@@@@@@")');
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[(" . $ruleXpath . ") and not(" . $this->contains($this->t('Fra')) . ")][1]", $root, true)));

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[" . $ruleXpath . "][1]/ancestor::td[1]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[" . $ruleXpath . "][1]/following::text()[normalize-space(.)][1]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[" . $ruleXpath . "][2]/following::text()[normalize-space(.)][1]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[(" . $ruleXpath . ") and not(contains(.,'{$this->t('Fra')}'))][1]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::td[({$this->contains($this->t('Udrejse'))}) and not(.//td)]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[({$ruleXpath}) and ({$this->contains($this->t('Fra'))})][1]/preceding::text()[normalize-space()!=''][1]", $root)));
            }

            if (!$date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[*[{$this->eq($this->t('Afrejse'))}] and *[{$this->contains($this->t('Fra'))}]][1]/preceding::tr[normalize-space()!=''][1]",
                    $root, true, "/^(?:.*:)?(\b\w+[\s,.]+\w+[\s,.]+\w+[\s,.]+\d{4})\s*$/u")));
            }

            if ($date) {
                $this->date = $date;
            }

            if (empty($this->date)) {
                $this->logger->debug('segment date not found');

                continue;
            }

            $conf = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Bookingnummer'))}][1]", $root);
            $confRoute = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Bookingnummer'))}][1]/ancestor::*[ *[normalize-space()][2] ][1][count(*[normalize-space()])=2]/*[normalize-space()][1]", $root, true, "/(?:^|\|\s*)([^\|]{3,}-[^\|]{3,})$/");
            $confTitle = preg_match("/^([^:]+?)\s*:.+/", $conf, $m)
                ? ($confRoute ? $m[1] . ' (' . $confRoute . ')' : $m[1])
                : null
            ;

            if (preg_match_all("/(?:\,|\:)\s*([A-Z\d]+)/u", $conf, $confNumber)) {
                if (count($confNumber[1]) == 1) {
                    $conf = $confNumber[1][0];

                    if (!in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
                        $f->general()->confirmation($conf, $confTitle);
                    }
                }

                if (count($confNumber[1]) > 1) {
                    foreach ($confNumber[1] as $conf) {
                        if (!in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
                            $f->general()->confirmation($conf, $confTitle);
                        }
                    }
                }
            } else {
                $conf = null;
            }

            if (empty($conf)) {
                $dep = $this->http->FindSingleNode("./ancestor::*[1]/tr[not(.//text()[{$this->eq($this->t('Afrejse'))}])][1]//td[1]/descendant::text()[normalize-space(.)!=''][1]", $root);
                $dep = preg_replace("#^\s*(\S{4,}) #", '$1', $dep);
                $arr = $this->http->FindSingleNode("./ancestor::*[1]/tr[count(td)>5][last()]//td[2]/descendant::text()[normalize-space(.)!=''][1]", $root);
                $arr = preg_replace("#^\s*(\S{4,}) #", '$1', $arr);

                if (!empty($dep) && !empty($arr)) {
                    $confsTitle = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Bookingnummer'))}][1][contains(., '{$dep}') and contains(., '{$arr}')]", null,
                            "#" . $this->opt($this->t('Bookingnummer')) . "\s+" . $dep . ".*\s+-\s+" . $arr . "#")));

                    if (count($confsTitle) == 1) {
                        $conf = $this->http->FindSingleNode("./preceding::text()[{$this->starts($confsTitle[0])}][1]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]+)\s*$#");
                    }
                }
            }

            if (!empty($conf) && !in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()->confirmation($conf);
            }

            // Departure
            $node = $this->http->FindNodes("./td[1]//text()[normalize-space(.)!='']", $root);

            if (isset($node[0]) || isset($node[1])) {
                $s->departure()
                    ->noCode()
                    ->name(($node[0] ?? '') . (!empty($node[1]) ? ', ' . $node[1] : ''));
            }
            $termanalTitle = array_merge((array) $this->t('Terminal'), ['Terminal']);

            if (isset($node[2])) {
                $s->departure()
                    ->terminal(trim(preg_replace('#(?:\s*\b|\s+)' . $this->opt($termanalTitle) . '(?:\b\s*|\s+)#iu', ' ', $node[2])));
            }
            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space(.)!='']", $root));

            if (
                preg_match("#(\d+:\d+(?:.*?)?)(?:\n(\d+\s+\w+|\w+\s+\d+|(?:.*\D|)\d{4}(?:\D.*|))|$)#u", $node, $m)
            ) {
                if (!empty($m[2])) {
                    $date = strtotime($this->normalizeDate($m[2]));
                }
                $s->departure()
                    ->date(strtotime($m[1], $date));
            }

            // Arrival
            $node = $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root);

            if (isset($node[0]) || isset($node[1])) {
                $s->arrival()
                    ->noCode()
                    ->name(($node[0] ?? '') . (!empty($node[1]) ? ', ' . $node[1] : ''));
            }

            if (isset($node[2])) {
                $s->arrival()
                    ->terminal(trim(preg_replace('#(?:\s*\b|\s+|^)' . $this->opt($termanalTitle) . '(?:\b\s*|\s+)#iu', ' ', $node[2])));
            }

            $node = implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(\d+:\d+(?:.*?)?)(?:\n(\d+\s+\w+|\w+\s+\d+|(?:.*\D|)\d{4}(?:\D.*|))|$)#u", $node, $m)
            ) {
                // 14:25   2022年8月16日
                if (!empty($m[2])) {
                    $date = strtotime($this->normalizeDate($m[2]));
                }
                $s->arrival()
                    ->date(strtotime($m[1], $date));
            }

            // Airline
            $node = $this->http->FindSingleNode("./td[5]", $root);

            if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $operator = $this->http->FindSingleNode("./td[6]", $root, true, "#^(?:.*{$this->opt($this->t('Operator'))}|)\s*(.+)#");

            if (preg_match("#^\s*(.+?) (DBA|For|Trafikeras av) .+#", $operator, $m)) {
                $s->airline()->operator($m[1]);
            } else {
                $s->airline()->operator($operator);
            }

            // Extra
            if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                $seatsXpath = "./following::*[self::td or self::th][" . $this->eq($this->t("Siddeplads")) . "]/ancestor::tr[1]/following-sibling::tr";
                $sNodes = $this->http->XPath->query($seatsXpath, $root);
                $dep = $this->re("/^\s*([^,]{3,}?),/", $s->getDepName());
                $arr = $this->re("/^\s*([^,]{3,}?),/", $s->getArrName());

                foreach ($sNodes as $row) {
                    $route = $this->http->FindSingleNode("td[1]", $row);
                    $routeParts = preg_split("/\s+-\s+/", $route);

                    if (count($routeParts) !== 2) {
                        continue;
                    }

                    if (!empty($dep) && preg_match("/^{$this->opt($routeParts[0])}/", $dep)
                        && !empty($arr) && preg_match("/^{$this->opt($routeParts[1])}/", $arr)
                    ) {
                        $seats = array_filter($this->http->FindNodes("td[3]/descendant::text()[normalize-space()]", $row, "/^\s*(\d+[A-Z])\s*$/"));

                        if (!empty($seats)) {
                            $s->extra()->seats($seats);
                        }
                    }
                }
            }
        }

        return $email;
    }

    private function hotel(Email $email)
    {
        if (empty($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Hotel")) . " or " . $this->eq($this->t("Room Type")) . "])[1]"))) {
            return $email;
        }

        $xpath = "//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::*[.//text()[" . $this->eq($this->t("Hotel")) . "]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Voucher No.'))}][1]", $root, true, "#" . $this->opt($this->t("Voucher No.")) . "\s*([A-Z\d]+)#"))
                ->travellers(array_values($this->http->FindNodes("//text()[" . $this->eq($this->t("Guest Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[2]//text()[normalize-space()]", $root)), true)
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Ordre dato'))}]/following::text()[normalize-space(.)!=''][1]"))))
            ;
            $confs = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Guest Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[4]//text()[normalize-space()]", $root)));

            foreach ($confs as $conf) {
                $h->general()->confirmation($conf);
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//img[1]/preceding::text()[normalize-space()][1]", $root))
                ->address(implode(", ", $this->http->FindNodes(".//img[1]/following::table[contains(normalize-space(), 'Lähtö:')][1]/following::text()[normalize-space()][1]/ancestor::div[2][count(.//text()[normalize-space()]) <4]//text()[normalize-space()]", $root)))
            ;

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Saapuminen:'))}]/following::text()[normalize-space(.)!=''][1]", $root))))
                ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Lähtö:'))}]/following::text()[normalize-space(.)!=''][1]", $root))))
                ->guests(array_sum($this->http->FindNodes("//text()[" . $this->eq($this->t("Guest Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]//text()[normalize-space()]", $root, "#^\s*(\d{1,2})\s*$#")))
            ;

            // Rooms
            $rXpath = ".//text()[" . $this->eq($this->t("Room Type")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]";
            $rNodes = $this->http->XPath->query($rXpath, $root);

            foreach ($rNodes as $r) {
                $h->addRoom()->setType($r->nodeValue);
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang(): bool
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $dBody[0] . '")]')->length > 0
                && $this->http->XPath->query('//*[contains(normalize-space(.),"' . $dBody[1] . '")]')->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function getProvider()
    {
        foreach (self::$detectFrom as $code => $emailFroms) {
            $emailFromsName = array_map(function ($v) {return trim($v, '.@'); }, $emailFroms);

            if ($this->http->XPath->query(
                    "(//text()[" . $this->starts($this->t('Ordrenummer')) . "])[1]/preceding::img[" . $this->contains($emailFromsName, '@src') . "] "
                    . "| //a[" . $this->contains(str_replace('@', '.', $emailFroms), '@href') . "] "
                    . "| (//text()[" . $this->eq($this->t('Ordrenummer')) . "])[1]/preceding::img[" . $this->eq($emailFromsName, '@alt') . " or " . $this->eq($emailFromsName, '@altx') . "]"
            )->length > 0) {
                return $code;
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return null;
        }

        if ($this->lang == 'th') {
            $year = $this->re("/^\D+\s+\d+\s*\D+\,\s*(\d{4})$/", $date);

            if ($year > date('Y')) {
                $year -= 543;
            }

            $date = preg_replace("/(\d{4})$/", $year, $date);
        }

        $year = date('Y', $this->date);
        $in = [
            '#^.*?\s+(\d+)[\.,]*\s+(\w+)[\.,]*\s+(\d{4})\s*$#u', //Dienstag 17 November, 2015
            '#^\s*(\d+)\s+(\w+)\.?\s*$#u', //18 Nov - for nextday arrive/depart
            '#^\s*(\w+)\s+(\d+)\.?\s*$#u', //18 Nov - for nextday arrive/depart
            '/^\s*(\d{4})[ ]*(?:년|年)[ ]*(\d{1,2})[ ]*(?:월|月)[ ]*(\d{1,2})[ ]*(?:일|日)\s*$/u', // 2019년11월16일
            '/^\D+\s+(\d+)\s*(\D+)\,\s*(\d{4})$/u', //วันเสาร์ 18 มิถุนายน, 2565
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 ' . $year,
            '$2 $1 ' . $year,
            '$1-$2-$3',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
//         $this->logger->debug('OUT' . $str);

        return $str;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("MX$", "MXN", $node);
        $node = str_replace("AU$", "AUD", $node);
        $node = str_replace("US$", "USD", $node);
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("zł", "PLN", $node);
        $node = str_replace("SG$", "SGD", $node);
        $node = str_replace("₽", "RUB", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("Kč", "CZK", $node);
        $node = str_replace("￥", "JPY", $node);
        $node = str_replace("₴", "UAH", $node);
        $node = str_replace("₺", "TRY", $node);
        $node = str_replace("฿", "THB", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#\b(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})\b#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "{$text} = \"{$s}\""; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;
        //		return '(?:' . implode("|", array_map(function($s){ return "(?:".preg_quote($s).")"; }, $field)) . ')';
        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "starts-with({$text}, \"{$s}\")"; }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }
}
