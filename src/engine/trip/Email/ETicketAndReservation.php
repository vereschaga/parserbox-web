<?php

namespace AwardWallet\Engine\trip\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketAndReservation extends \TAccountChecker
{
    public $mailFiles = "trip/it-11249377.eml, trip/it-11264547.eml, trip/it-11289637.eml, trip/it-11321536.eml, trip/it-22790372.eml, trip/it-23135857.eml, trip/it-24925792.eml, trip/it-26603409.eml, trip/it-26743336.eml, trip/it-8009629.eml, trip/it-8057595.eml, trip/it-8059345.eml, trip/it-8083494.eml, trip/it-8089634.eml, trip/it-8092216.eml, trip/it-8122117.eml, trip/it-8122121.eml, trip/it-8146700.eml, trip/it-8172202.eml, trip/it-8172206.eml, trip/it-8188064.eml, trip/it-8192590.eml, trip/it-8205332.eml, trip/it-8206483.eml, trip/it-8206485.eml, trip/it-8207075.eml, trip/it-8213660.eml, trip/it-8219283.eml, trip/it-8223748.eml, trip/it-8247461.eml, trip/it-8258941.eml, trip/it-8311536.eml, trip/it-8343835.eml, trip/it-8364920.eml, trip/it-8384631.eml, trip/it-8446933.eml, trip/it-8529615.eml, trip/it-8556709.eml, trip/it-8556713.eml, trip/it-8561134.eml, trip/it-8572176.eml";

    public $reFrom = [
        "flights@mytrip.com",
        "flights@trip.ru",
        "flights@airtickets24.com",
        "flights@avion.ro",
        "flights@trip.ae",
        "flights@trip.ua",
        "flights@trip.kz",
        "flights@pamediakopes.gr",
    ];
    public $reSubject = [
        "ru"  => "Ваши электронные билеты",
        "ru2" => "Подтверждение бронирования",
        "ro"  => "Biletele dvs. electronice pentru",
        "ro2" => "Rezervare temporară pentru",
        "en"  => "Your e-tickets for",
        "en2" => "Reservation confirmation for",
        "pl"  => "Bilety elektroniczne do",
        "nl"  => "Uw e-tickets voor",
        "es"  => "Sus billetes electrónicos para",
        "fi"  => "Sinun e-lippusi kohteeseen",
        "pt"  => "Os seus e-tickets para",
        "hr"  => "Vaše e-karte za",
        "it"  => "I tuoi e-ticket per",
        "hu"  => "Az Ön e-jegyei",
        "ja"  => "eチケット",
        "fr"  => "Vos e-billets pour",
        "fr2" => "Confirmation de réservation pour",
        "de"  => "Ihre E-Tickets für",
        "da"  => "Din(e) e-billetter til",
        "da2" => "Reservation bekræftelse for",
        "sv"  => "Dina e-biljetter för",
        "bg"  => "Вашите е-билети до",
        "uk"  => "Ваш електронний квиток",
        "el"  => "Τα ηλεκτρονικά σας εισιτήρια",
        "sr"  => "Ваше e-карте за",
        "cs"  => "Vaše elektronická letenka do",
        "et"  => "Teie e-piletid sihtkohta ",
        "zh"  => "电子机票",
        "no"  => "Reservasjonsbekreftelse for",
        "tr"  => "için e-biletiniz",
    ];
    public $reBody = [
        "Mytrip.com",
        "Trip.ru",
        "Airtickets24.com",
        "Pamediakopes.gr",
        "Avion.ro",
        "Trip.ua",
        "Trip.ae",
        "Trip.kz",
    ];
    public $langDetectorsHtml = [
        "ru" => ["Фамилия - Имя - Обращение"],
        "ro" => ["Nume de familie - Prenume - Titlu"],
        "en" => ["Surname - Name - Title"],
        "pl" => ["Nazwisko - Imię - Tytuł"],
        "nl" => ["Achternaam - Naam - Titel"],
        "es" => ["Apellidos - Nombre - Título"],
        "fi" => ["Sukunimi - Nimi - Titteli"],
        "pt" => ["Apelido - Nome - Título"],
        "hr" => ["Prezime - Ime - Titula"],
        "it" => ["Cognome - Nome - Titolo"],
        "hu" => ["Vezetéknév - Utónév - Megszólítás"],
        "ja" => ["姓 - 名 - 称号"],
        "fr" => ["Nom - Prénom - Titre", "Prénom - Nom - Titre"],
        "de" => ["Nachname - Name - Titel"],
        "da" => ["Efternavn - Navn - Titel"],
        "sv" => ["Efternamn - Namn - Titel"],
        "bg" => ["Фамилия - Име - Обръщение"],
        "uk" => ["Прізвище - Ім'я - Звертання"],
        "el" => ["Επώνυμο - Όνομα - Τίτλος"],
        "sr" => ["Презиме - Име - Назив"],
        "cs" => ["Příjmení - Jméno - Titul"],
        "et" => ["Perekonnanimi - Nimi- Tiitel"],
        "zh" => ["姓-名-头衔"],
        "no" => ["Navn - Etternavn - Tittel"],
        "tr" => ["Soyadı - İsim - Unvan"],
    ];
    public $langDetectorsPdf = [
        "ru" => ["маршрутная квитанция"],
        "fi" => ["e-lipun reseptiluonnos"],
        "nl" => ["e-ticket reisschema ontvangstbewijs"],
        "sr" => ["признаница за плаћену е-карту"],
        "da" => ["E-billet rejsekvittering"],
        "hr" => ["priznanica za e-kartu"],
        "et" => ["e-pileti marsruudi kviitung"],
        "it" => ["Ricevuta itinerario e-ticket"],
        "pl" => ["potwierdzenie biletu elektronicznego"],
        "de" => ["E-Ticket Reisebstätigung"],
        "fr" => ["Billet électronique - reçu d'itinéraire"],
        "pt" => ["recibo de itinerário e-ticket"],
        "tr" => ["E-bilet kesme makbuzu"],
        "es" => ["e-ticket recibo de itinerario"],
        "en" => ["e-ticket itinerary receipt"], // last
    ];

    public static $dictionary = [
        "ru" => [
            //			"Код бронирования:" => "",
            //			"Данные пассажиров" => "",
            //			"Пассажиры и их билеты" => "",
            //			"Электронные билеты" => "",
            //			"Вылет" => "",
            //			"Обратно" => "",
            //			"ОТПРАВЛЕНИЕ" => "",
            //			"ВОЗВРАЩЕНИЕ" => "",
            //			"Терминал" => "",
            //			"Рейс" => "",
            //			"Рейс выполняется авиакомпанией" => "",
            //			"Итого" => "",
            "dayOfWeek" => [0 => 'Вс', 1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'],
            //			"Фамилия - Имя - Обращение" => "",
            //			"Онлайн-регистрация" => "",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "ro" => [
            "Код бронирования:"              => "Codul rezervării:",
            "Данные пассажиров"              => "Date pasageri",
            "Пассажиры и их билеты"          => "Pasageri & bilete electronice",
            "Электронные билеты"             => "Bilete electronice",
            "Вылет"                          => "Plecare",
            "Обратно"                        => "Întoarcere",
            "ОТПРАВЛЕНИЕ"                    => "PLECARE",
            "ВОЗВРАЩЕНИЕ"                    => "ÎNTOARCERE",
            "Рейс"                           => "Zbor",
            "Рейс выполняется авиакомпанией" => "Efectuat de",
            "Итого"                          => 'Total',
            "dayOfWeek"                      => [0 => 'Dum', 1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Joi', 5 => 'Vin', 6 => 'Sâm'],
            "Фамилия - Имя - Обращение"      => "Nume de familie - Prenume - Titlu",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "en" => [
            "Код бронирования:"              => "Booking reference code:",
            "Данные пассажиров"              => "Passengers' information",
            "Пассажиры и их билеты"          => "Passengers & e-tickets",
            "Электронные билеты"             => "E-tickets",
            "Вылет"                          => "Departure",
            "Обратно"                        => "Return",
            "ОТПРАВЛЕНИЕ"                    => "DEPARTURE",
            "ВОЗВРАЩЕНИЕ"                    => "RETURN",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Flight",
            "Рейс выполняется авиакомпанией" => "Operated by",
            "Итого"                          => "Total",
            'dayOfWeek'                      => [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'],
            "Фамилия - Имя - Обращение"      => "Surname - Name - Title",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "pl" => [
            "Код бронирования:"              => "Numer rezerwacji:",
            "Данные пассажиров"              => "Informacje dla pasażerów",
            "Пассажиры и их билеты"          => "Pasażerowie i numery biletów",
            "Электронные билеты"             => "Numery biletów",
            "Вылет"                          => "Wylot",
            "Обратно"                        => "Powrót",
            "ОТПРАВЛЕНИЕ"                    => "ODLOT",
            "ВОЗВРАЩЕНИЕ"                    => "POWRÓT",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Lot",
            "Рейс выполняется авиакомпанией" => "Obsługiwany przez",
            "Итого"                          => "Razem",
            'dayOfWeek'                      => [0 => 'nie', 1 => 'pon', 2 => 'wto', 3 => 'śro', 4 => 'czw', 5 => 'pia', 6 => 'sob'],
            "Фамилия - Имя - Обращение"      => "Nazwisko - Imię - Tytuł",
            "Онлайн-регистрация"             => "Odprawa online",
            // Pdf
            "ВНИМАНИЕ!" => "UWAGA!",
            "Пересадка" => "Przesiadka",
        ],
        "nl" => [
            "Код бронирования:" => ["Boekingsreferentiecode:", "Boekingspreferentiecode:"],
            "Данные пассажиров" => "Passagiersinformatie",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "E-tickets",
            "Вылет"                          => "Vertrek",
            "Обратно"                        => "Retour",
            "ОТПРАВЛЕНИЕ"                    => "VERTREK",
            "ВОЗВРАЩЕНИЕ"                    => "RETOUR",
            "Терминал"                       => ["Terminal", "Automaat"],
            "Рейс"                           => "Vlucht",
            "Рейс выполняется авиакомпанией" => "Beheerd door",
            "Итого"                          => "Totaal",
            'dayOfWeek'                      => [0 => 'zon', 1 => 'maa', 2 => 'din', 3 => 'woe', 4 => 'don', 5 => 'vri', 6 => 'zat'],
            "Фамилия - Имя - Обращение"      => "Achternaam - Naam - Titel",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            "ВНИМАНИЕ!" => "OPGELET!",
            "Пересадка" => "Stop",
        ],
        "es" => [
            "Код бронирования:" => "Código de reserva:",
            "Данные пассажиров" => ["Datos de Pasajeros", "Datos de los Pasajeros"],
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "Billetes electrónicos",
            "Вылет"                          => "Salida",
            "Обратно"                        => "Regreso",
            "ОТПРАВЛЕНИЕ"                    => "SALIDA",
            "ВОЗВРАЩЕНИЕ"                    => "VUELTA",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Vuelo",
            "Рейс выполняется авиакомпанией" => "Operado por",
            "Итого"                          => "Total",
            'dayOfWeek'                      => [0 => 'dom', 1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb'],
            "Фамилия - Имя - Обращение"      => "Apellidos - Nombre - Título",
            "Онлайн-регистрация"             => "Web registro",
            // Pdf
            "ВНИМАНИЕ!" => "¡ATENCIÓN!",
            "Пересадка" => "Escala",
        ],
        "fi" => [
            "Код бронирования:"     => "Varauksen viitekoodi:",
            "Данные пассажиров"     => "Matkustajan tiedot",
            "Пассажиры и их билеты" => "Matkustajat & e-liput",
            "Электронные билеты"    => "E-liput",
            "Вылет"                 => "Lähtö",
            "Обратно"               => "Paluu",
            "ОТПРАВЛЕНИЕ"           => "LÄHTÖ",
            //			"ВОЗВРАЩЕНИЕ" => "",
            "Терминал"                       => "Terminaali",
            "Рейс"                           => "Valitse kohde",
            "Рейс выполняется авиакомпанией" => "Ylläpitäjä",
            "Итого"                          => "Yhteensä",
            'dayOfWeek'                      => [0 => 'su', 1 => 'ma', 2 => 'ti', 3 => 'ke', 4 => 'to', 5 => 'pe', 6 => 'la'],
            "Фамилия - Имя - Обращение"      => "Sukunimi - Nimi - Titteli",
            "Онлайн-регистрация"             => "Lähtöselvitys verkossa",
            // Pdf
            "ВНИМАНИЕ!" => "HUOMIO!",
            "Пересадка" => "Välilasku",
        ],
        "pt" => [
            "Код бронирования:" => "Código de referência de reserva:",
            "Данные пассажиров" => "Informação dos passageiros",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "E-tickets",
            "Вылет"                          => "Partida",
            "Обратно"                        => "Regresso",
            "ОТПРАВЛЕНИЕ"                    => "PARTIDA",
            "ВОЗВРАЩЕНИЕ"                    => "REGRESSAR",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Voo",
            "Рейс выполняется авиакомпанией" => "Operado por",
            "Итого"                          => "Total",
            'dayOfWeek'                      => [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'],
            "Фамилия - Имя - Обращение"      => "Apelido - Nome - Título",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            //			"ВНИМАНИЕ!" => "ATENÇÃO!",
            "Пересадка" => "Paragem",
        ],
        "hr" => [
            "Код бронирования:" => "Referentni rezervacijski kod:",
            "Данные пассажиров" => "Podatci o putnicima",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "E-karte",
            "Вылет"                          => "Odlazak",
            "Обратно"                        => "Povratak",
            "ОТПРАВЛЕНИЕ"                    => "ODLAZAK",
            "ВОЗВРАЩЕНИЕ"                    => "POVRATAK",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Let",
            "Рейс выполняется авиакомпанией" => "Upravlja",
            "Итого"                          => "Ukupno",
            'dayOfWeek'                      => [0 => 'Ned', 1 => 'Pon', 2 => 'Uto', 3 => 'Sre', 4 => 'Čet', 5 => 'Pet', 6 => 'Sub'],
            "Фамилия - Имя - Обращение"      => "Prezime - Ime - Titula",
            "Онлайн-регистрация"             => "Internetska prijava",
            // Pdf
            "ВНИМАНИЕ!" => "PAŽNJA!",
            "Пересадка" => "Zaustavljanje",
        ],
        "it" => [
            "Код бронирования:"              => "Codice di riferimento prenotazione:",
            "Данные пассажиров"              => "Informazioni Passeggeri",
            "Пассажиры и их билеты"          => "Passeggeri & E-Ticket",
            "Электронные билеты"             => "E-ticket",
            "Вылет"                          => "Partenza",
            "Обратно"                        => "Ritorno",
            "ОТПРАВЛЕНИЕ"                    => "PARTENZA",
            "ВОЗВРАЩЕНИЕ"                    => "RITORNO",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Volo",
            "Рейс выполняется авиакомпанией" => "Gestito da",
            "Итого"                          => "Totale",
            'dayOfWeek'                      => [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab'],
            "Фамилия - Имя - Обращение"      => "Cognome - Nome - Titolo",
            "Онлайн-регистрация"             => "Check-in online",
            // Pdf
            "ВНИМАНИЕ!" => "ATTENZIONE!",
            "Пересадка" => "Scalo",
        ],
        "hu" => [
            "Код бронирования:" => "Foglalási referenciakód:",
            "Данные пассажиров" => "Utasok adatai",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "E-jegyek",
            "Вылет"              => "Indulás",
            "Обратно"            => "Visszaút",
            "ОТПРАВЛЕНИЕ"        => "INDULÁS",
            "ВОЗВРАЩЕНИЕ"        => "VISSZA",
            "Терминал"           => "Terminál",
            "Рейс"               => "Járat",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Összesen",
            'dayOfWeek'                 => [0 => 'v', 1 => 'h', 2 => 'k', 3 => 'sze', 4 => 'cs', 5 => 'p', 6 => 'szo'],
            "Фамилия - Имя - Обращение" => "Vezetéknév - Utónév - Megszólítás",
            "Онлайн-регистрация"        => ["Internetes utasfelvétel", "Internetes becsekkolás"],
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "ja" => [
            "Код бронирования:" => "予約参照番号:",
            "Данные пассажиров" => "乗客情報",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "eチケット",
            "Вылет"              => "出発",
            "Обратно"            => "お戻り",
            "ОТПРАВЛЕНИЕ"        => "往路",
            "ВОЗВРАЩЕНИЕ"        => "復路",
            "Терминал"           => "ターミナル",
            "Рейс"               => "フライト",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "合計",
            'dayOfWeek'                 => [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'],
            "Фамилия - Имя - Обращение" => "姓 - 名 - 称号",
            "Онлайн-регистрация"        => "オンライン・チェックイン",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "fr" => [
            "Код бронирования:" => ["Code de référence de la réservation:", "Booking reference code:"],
            "Данные пассажиров" => "Informations sur les passagers",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "E-billets",
            "Вылет"              => "Départ",
            "Обратно"            => "Retour",
            "ОТПРАВЛЕНИЕ"        => "DÉPART",
            "ВОЗВРАЩЕНИЕ"        => "RETOUR",
            "Терминал"           => "Terminal",
            "Рейс"               => "Vol",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Total",
            'dayOfWeek'                 => [0 => 'dim', 1 => 'lun', 2 => 'mar', 3 => 'mer', 4 => 'jeu', 5 => 'ven', 6 => 'sam'],
            "Фамилия - Имя - Обращение" => ["Nom - Prénom - Titre", "Prénom - Nom - Titre"],
            "Онлайн-регистрация"        => "Enregistrement web",
            // Pdf
            "ВНИМАНИЕ!" => "ATTENTION!",
            "Пересадка" => "Correspondance",
        ],
        "de" => [
            "Код бронирования:" => "Buchungsnummer:",
            "Данные пассажиров" => "Passagierangaben",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "E-Tickets",
            "Вылет"                          => "Hinflug",
            "Обратно"                        => "Rückflug",
            "ОТПРАВЛЕНИЕ"                    => "ABREISE",
            "ВОЗВРАЩЕНИЕ"                    => "ZURÜCK",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Flug",
            "Рейс выполняется авиакомпанией" => "Durchgeführt von",
            "Итого"                          => "Gesamt",
            'dayOfWeek'                      => [0 => 'So', 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa'],
            "Фамилия - Имя - Обращение"      => "Nachname - Name - Titel",
            "Онлайн-регистрация"             => "Online Check-in",
            // Pdf
            "ВНИМАНИЕ!" => "ACHTUNG!",
            "Пересадка" => "Stop",
        ],
        "da" => [
            "Код бронирования:"              => "Bestilling referencekode:",
            "Данные пассажиров"              => "Passager information",
            "Пассажиры и их билеты"          => "Passagerer og e-billetter",
            "Электронные билеты"             => "E-billet",
            "Вылет"                          => "Afrejse",
            "Обратно"                        => "Retur",
            "ОТПРАВЛЕНИЕ"                    => "AFREJSE",
            "ВОЗВРАЩЕНИЕ"                    => "TILBAGE",
            "Терминал"                       => "Terminal",
            "Рейс"                           => "Flyv",
            "Рейс выполняется авиакомпанией" => "Betjenes af",
            "Итого"                          => "Total",
            'dayOfWeek'                      => [0 => 'søn', 1 => 'man', 2 => 'tir', 3 => 'ons', 4 => 'tor', 5 => 'fre', 6 => 'lør'],
            "Фамилия - Имя - Обращение"      => "Efternavn - Navn - Titel",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            "ВНИМАНИЕ!" => "OBS",
            "Пересадка" => "Mellemlanding",
        ],
        "sv" => [
            "Код бронирования:" => "Bokningsreferenskod:",
            "Данные пассажиров" => "Information om Passagerare",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "E-biljetter",
            "Вылет"              => "Avgång",
            //			"Обратно" => "",
            "ОТПРАВЛЕНИЕ" => "AVGÅNG",
            //			"ВОЗВРАЩЕНИЕ" => "",
            "Терминал" => "Terminal",
            "Рейс"     => "Flyg",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Total",
            'dayOfWeek'                 => [0 => 'sön', 1 => 'mån', 2 => 'tis', 3 => 'ons', 4 => 'tor', 5 => 'fre', 6 => 'lör'],
            "Фамилия - Имя - Обращение" => "Efternamn - Namn - Titel",
            "Онлайн-регистрация"        => "Webb-incheckning",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "bg" => [
            "Код бронирования:" => "Код на резервация:",
            "Данные пассажиров" => "Информация за пътниците ",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "Електронни билети",
            "Вылет"                          => "Заминаване",
            "Обратно"                        => "Връщане",
            "ОТПРАВЛЕНИЕ"                    => "ЗАМИНАВАНЕ",
            "ВОЗВРАЩЕНИЕ"                    => "ВРЪЩАНЕ",
            "Терминал"                       => "Терминал",
            "Рейс"                           => "Полет",
            "Рейс выполняется авиакомпанией" => "Опериран от",
            "Итого"                          => "Oбща",
            'dayOfWeek'                      => [0 => 'нeд', 1 => 'пон', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пет', 6 => 'съб'], // to check 0,1,3,4
            "Фамилия - Имя - Обращение"      => "Фамилия - Име - Обръщение",
            "Онлайн-регистрация"             => "Уеб чек-ин",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "uk" => [
            "Код бронирования:" => "Код бронювання:",
            "Данные пассажиров" => "Дані пасажирів",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "Електронні квитки",
            "Вылет"                          => "Відправлення",
            "Обратно"                        => "Повернення",
            "ОТПРАВЛЕНИЕ"                    => "ВІДПРАВЛЕННЯ",
            "ВОЗВРАЩЕНИЕ"                    => "ПОВЕРНЕННЯ",
            "Терминал"                       => "Термінал",
            "Рейс"                           => "Рейс",
            "Рейс выполняется авиакомпанией" => "Виконується",
            "Итого"                          => "Загалом",
            "dayOfWeek"                      => [0 => 'нд', 1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб'],
            "Фамилия - Имя - Обращение"      => "Прізвище - Ім'я - Звертання",
            "Онлайн-регистрация"             => "Онлайн регістрація",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "el" => [
            "Код бронирования:"              => "Κωδικός κράτησης:",
            "Данные пассажиров"              => "Στοιχεία Επιβατών",
            "Пассажиры и их билеты"          => "Επιβάτες & ηλεκτρονικά εισιτήρια",
            "Электронные билеты"             => "Ηλεκτρονικά εισιτήρια",
            "Вылет"                          => "Αναχώρηση",
            "Обратно"                        => "Επιστροφή",
            "ОТПРАВЛЕНИЕ"                    => "ΑΝΑΧΩΡΗΣΗ",
            "ВОЗВРАЩЕНИЕ"                    => "ΕΠΙΣΤΡΟΦΗ",
            "Терминал"                       => "Τερματικό",
            "Рейс"                           => "Πτήση",
            "Рейс выполняется авиакомпанией" => "Εκτελείται από",
            "Итого"                          => "Σύνολο",
            "dayOfWeek"                      => [0 => 'Κυρ', 1 => 'Δευ', 2 => 'Τρι', 3 => 'Τετ', 4 => 'Πεμ', 5 => 'Παρ', 6 => 'Σαβ'],
            "Фамилия - Имя - Обращение"      => "Επώνυμο - Όνομα - Τίτλος",
            "Онлайн-регистрация"             => "Web check-in",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "sr" => [
            "Код бронирования:" => "Референтни код резервације:",
            "Данные пассажиров" => "Информације о путницима",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты"             => "Е-карте",
            "Вылет"                          => "Одлазак",
            "Обратно"                        => "Повратак",
            "ОТПРАВЛЕНИЕ"                    => "ОДЛАЗАК",
            "ВОЗВРАЩЕНИЕ"                    => "ПОВРАТАК",
            "Терминал"                       => "Терминал",
            "Рейс"                           => "Лет",
            "Рейс выполняется авиакомпанией" => "Обавља",
            "Итого"                          => "Укупно",
            "dayOfWeek"                      => [0 => 'Нед', 1 => 'Пон', 2 => 'Уто', 3 => 'Сре', 4 => 'Чет', 5 => 'Пет', 6 => 'Суб'],
            "Фамилия - Имя - Обращение"      => "Презиме - Име - Назив",
            "Онлайн-регистрация"             => "Пријављивање путем интернета",
            // Pdf
            "ВНИМАНИЕ!" => "ПАЖЊА!",
            "Пересадка" => "Заустављање",
        ],
        "cs" => [
            "Код бронирования:" => "Rezervační kód:",
            "Данные пассажиров" => "Údaje o cestujících",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "Údaje o cestujících",
            "Вылет"              => "Odlet",
            "Обратно"            => "Návrat",
            "ОТПРАВЛЕНИЕ"        => "ODLET",
            "ВОЗВРАЩЕНИЕ"        => "NÁVRAT",
            "Терминал"           => "Terminál",
            "Рейс"               => "Let",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Celkem",
            "dayOfWeek"                 => [0 => 'Ne', 1 => 'Po', 2 => 'Út', 3 => 'St', 4 => 'Čt', 5 => 'Pá', 6 => 'So'],
            "Фамилия - Имя - Обращение" => "Příjmení - Jméno - Titul",
            "Онлайн-регистрация"        => "Пријављивање путем интернета",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "et" => [
            "Код бронирования:" => "Broneeringu viitekood:",
            "Данные пассажиров" => "Reisijainfo",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "E-piletid",
            "Вылет"              => "Väljumine",
            "Обратно"            => "Tagasi",
            "ОТПРАВЛЕНИЕ"        => "VÄLJUMINE",
            "ВОЗВРАЩЕНИЕ"        => "TAGASI",
            "Терминал"           => "Terminal",
            "Рейс"               => "Lend",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Kokku",
            "dayOfWeek"                 => [0 => 'P', 1 => 'E', 2 => 'T', 3 => 'K', 4 => 'N', 5 => 'R', 6 => 'L'],
            "Фамилия - Имя - Обращение" => "Perekonnanimi - Nimi- Tiitel",
            "Онлайн-регистрация"        => "Registreerimine veebis",
            // Pdf
            "ВНИМАНИЕ!" => "TÄHELEPANU!",
            "Пересадка" => "Peatus",
        ],
        "zh" => [
            "Код бронирования:" => "预订参考码:",
            "Данные пассажиров" => "乘客信息",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "电子机票",
            "Вылет"              => "出发",
            "Обратно"            => "返程",
            "ОТПРАВЛЕНИЕ"        => "出发",
            "ВОЗВРАЩЕНИЕ"        => "返回",
            "Терминал"           => "航站楼",
            "Рейс"               => "航班",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "总计",
            "dayOfWeek"                 => [0 => '周日', 1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六'],
            "Фамилия - Имя - Обращение" => "姓-名-头衔",
            "Онлайн-регистрация"        => "在线登机",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "no" => [
            "Код бронирования:" => "Referansekode ved bestilling:",
            "Данные пассажиров" => "passasjer informasjon",
            //			"Пассажиры и их билеты" => "",
            //			"Электронные билеты" => "",
            //			"Вылет" => "",
            //			"Обратно" => "",
            "ОТПРАВЛЕНИЕ" => "AVREISE",
            "ВОЗВРАЩЕНИЕ" => "TILBAKE",
            //			"Терминал" => "",
            "Рейс" => "Fly",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Total",
            "dayOfWeek"                 => [0 => 'søn', 1 => 'man', 2 => 'tir', 3 => 'ons', 4 => 'tor', 5 => 'fre', 6 => 'lør'],
            "Фамилия - Имя - Обращение" => "Navn - Etternavn - Tittel",
            //			"Онлайн-регистрация" => "",
            // Pdf
            //			"ВНИМАНИЕ!" => "",
            //			"Пересадка" => ""
        ],
        "tr" => [
            "Код бронирования:" => "Rezervasyon referans kodu:",
            "Данные пассажиров" => "Yolcu bilgileri",
            //			"Пассажиры и их билеты" => "",
            "Электронные билеты" => "E-bilet",
            "Вылет"              => "Gidiş",
            "Обратно"            => "Dönüş",
            "ОТПРАВЛЕНИЕ"        => "GİDİŞ",
            "ВОЗВРАЩЕНИЕ"        => "DÖNÜŞ",
            "Терминал"           => "Terminal",
            "Рейс"               => "Uçuş",
            //			"Рейс выполняется авиакомпанией" => "",
            "Итого"                     => "Toplam",
            "dayOfWeek"                 => [0 => 'Paz', 1 => 'Pzt', 2 => 'Sal', 3 => 'Çrş', 4 => 'Prş', 5 => 'Cum', 6 => 'Cts'],
            "Фамилия - Имя - Обращение" => "Soyadı - İsim - Unvan",
            "Онлайн-регистрация"        => "Web check-in",
            // Pdf
            "ВНИМАНИЕ!" => "DİKKAT!",
            "Пересадка" => "Duraklama",
        ],
    ];

    public $lang = '';
    public $date;
    public $emailType;
    public $pdfPattern = '.+\.pdf';

    public $total = [
        'Amount'   => 0.0,
        'Currency' => '',
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $findedFrom = false;

        foreach ($this->reFrom as $reFrom) {
            if (strpos($headers['from'], $reFrom) !== false) {
                $findedFrom = true;
            }
        }

        if ($findedFrom === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (empty($pdfs[0])) {
            $body = $parser->getHTMLBody();
            $findedProv = false;

            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $findedProv = true;
                }
            }

            if ($findedProv !== false && $this->assignLangHtml()) {
                return true;
            }
        }

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            $findedProv = false;

            foreach ($this->reBody as $reBody) {
                if (stripos($textPdf, $reBody) !== false) {
                    $findedProv = true;
                }
            }

            if ($findedProv === false) {
                continue;
            }

            if ($this->assignLangPdf($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $type = '';
        $its = [];

        // HTML

        if ($this->assignLangHtml() !== false) {
            if (empty($this->http->FindSingleNode("(//*[normalize-space(.)='" . $this->t("Электронные билеты") . "'])[1]"))) {
                $this->emailType = 'reservation';
            } else {
                $this->emailType = 'eTicket';
            }

            if (stripos(html_entity_decode($this->http->Response['body']), $this->t("Пассажиры и их билеты"))) {
                $type = 'Html2';
                $this->parseHtml_type2($its);
            } elseif ($this->strposArr(html_entity_decode($this->http->Response['body']), $this->t("Данные пассажиров"))) {
                $type = 'Html1';
                $this->parseHtml_type1($its);
            }
            $result = [
                'emailType'  => 'ETicketAndReservation' . $type . ucfirst($this->lang),
                'parsedData' => [
                    'Itineraries' => $its,
                ],
            ];

            if (!empty($this->total['Amount']) && !empty($this->total['Currency'])) {
                $result['parsedData']['TotalCharge'] = $this->total;
            }

            if (!empty($its) && !empty($its[0]['TripSegments']) && !empty($its[0]['TripSegments'][0]['DepDate'])) {
                return $result;
            } else {
                //				$its = [];
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (isset($pdfs[0])) {
            $type = 'Pdf';

            foreach ($pdfs as $pdf) {
                if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }

                if ($this->assignLangPdf($textPdf)) {
                    $this->parsePdf($its, $textPdf);
                }
            }
            $result = [
                'emailType'  => 'ETicketAndReservation' . $type . ucfirst($this->lang),
                'parsedData' => [
                    'Itineraries' => $its,
                ],
            ];

            if (!empty($this->total['Amount']) && !empty($this->total['Currency'])) {
                $result['parsedData']['TotalCharge'] = $this->total;
            } else {
                $total = $this->http->FindSingleNode("//text()[normalize-space(.) = '" . $this->t('Итого') . "' or normalize-space(.) = '" . $this->t('Итого') . ":']/following::text()[string-length(normalize-space())>0][1]");
                $this->setTotal($total);

                if (!empty($this->total['Amount']) && !empty($this->total['Currency'])) {
                    $result['parsedData']['TotalCharge'] = $this->total;
                }
            }

            if (!empty($its)) {
                return $result;
            }
            $this->total = [
                'Amount'   => 0.0,
                'Currency' => '',
            ];
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3; // 1 pdf + 2 type htnl
    }

    protected function getTotal($total)
    {
        $result = [
            'Amount'   => null,
            'Currency' => '',
        ];
        $total = str_replace("'", '.', $total);

        if ($this->lang == 'en' && strpos($total, '£') !== false) {
            $total = str_replace('£', '', $total) . ' GBP';
        }

        if ($this->lang == 'en' && strpos($total, '$') !== false) {
            $total = str_replace('£', '', $total) . ' USD';
        }

        if ($this->lang == 'ja' && strpos($total, '¥') !== false) {
            $total = str_replace('¥', '', $total) . ' JPY';
        }

        if ($this->lang == 'da' && strpos($total, 'kr.') !== false) {
            $total = str_replace('kr.', '', $total) . ' DDK';
        }
        $total = str_replace(
                [' руб', ' €', ' zł', ' Ft', 'Fr ', ' Kč'],
                [' RUB', ' EUR', ' PLN', ' HUF', 'CHF ', 'CZK'], $total);

        if (preg_match("#(?<amount>[\d ,.]+)\s*(?<currency>[A-Z]{3})#u", $total, $m) or preg_match("#(?<currency>[A-Z]{3})\s*(?<amount>[\d ,.]+)#u", $total, $m)) {
            $result['Amount'] = $this->normalizePrice($m['amount']);
            $result['Currency'] = $m['currency'];
        }

        return $result;
    }

    protected function setTotal($total)
    {
        $total = $this->getTotal($total);

        if (!empty($total['Amount']) && !empty($total['Currency'])) {
            $this->total['Amount'] = $total['Amount'];
            $this->total['Currency'] = $total['Currency'];
        }
    }

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    protected function calculateDate($dateStr)
    {
        $in = [
            "#^\s*(\w+)\.?\s*(\d{1,2})/(\d{2})\s*(\d+:\d+)\s*$#u", //Ср 18/05 07:05
            "#^\s*(\w+)\.?,\s*(\d{1,2})\s*(\w+).?\s*$#u", //Сб, 30 мая
        ];
        $out = [
            "$1 $2 $3 $4",
            "$1 $2 $3",
        ];
        $dateStr = preg_replace($in, $out, $dateStr);

        if (preg_match("#\d+\s+([^\d\s]+)#", $dateStr, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $dateStr = str_replace($m[1], $en, $dateStr);
            }
        }

        if (preg_match('#^(\w+)\s+(\d{1,2})\s+(\d{2}|\w+)\s*(\d+:\d+)?\s*$#u', $dateStr, $m) > 0) {
            if (empty($m[4])) {
                $m[4] = '';
            }
            $current = (int) date('Y', $this->date);

            for ($i = 0; $i < 3; $i++) {
                $foundDate = strtotime(sprintf('%s.%s.%s %s', $m[2], $m[3], $current + $i, $m[4]));

                if (strcasecmp($m[1], $this->t('dayOfWeek')[date('w', $foundDate)]) === 0) {
                    break;
                }
                $foundDate = strtotime(sprintf('%s.%s.%s %s', $m[2], $m[3], $current - $i, $m[4]));

                if (strcasecmp($m[1], $this->t('dayOfWeek')[date('w', $foundDate)]) === 0) {
                    break;
                }
                unset($foundDate);
            }

            if (isset($foundDate)) {
                return $foundDate;
            }
        }

        return strtotime($dateStr);
    }

    private function parsePdf(&$its, $text)
    {
        $tn = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Код бронирования:')) . "]/following::text()[string-length(normalize-space(.))>0][1]", null, true, "#[A-Z\d]{5,6}#");

        if (!empty($tn)) {
            $TripNumber = $tn;
        }

        if (preg_match("#\n((?:[\w ]+ / )?Passenger.*(?:(?:\n.*){1,10})?)(\n.*\(UTC.+)?\n.+\n[ ]*Ticket issued#u", $text, $m)) {
            $passInfo = $this->splitCols($m[1]);
        }

        if (empty($passInfo) || count($passInfo) < 2) {
            $this->http->log("incorrect passInfo table parse");

            return;
        }

        if (preg_match("#Passenger\s+([\s\S]+)#", $passInfo[0], $m)) {
            $Passengers = trim(preg_replace("#\s+#", ' ', $m[1]));
        }

        $ticketText = (isset($passInfo[2])) ? $passInfo[2] : $passInfo[1];

        if (preg_match("#" . $this->t(" / e-ticket number") . "\s+([\d- ]+)#", $ticketText, $m)) {
            $TicketNumbers = $m[1];
        }

        $pos = mb_strpos($text, ' / Fare');

        if (preg_match("# \/ Fare[ ]*(.+)\s+\w+ \/ Taxes[ ]*(.+)#u", substr($text, $pos), $m)) {
            $total = $this->getTotal($m[1]);

            if (!empty($total['Amount']) && !empty($total['Currency'])) {
                $this->total['Amount'] += $total['Amount'];
                $this->total['Currency'] = $total['Currency'];
            }
            $total = $this->getTotal($m[2]);

            if (!empty($total['Amount']) && !empty($total['Currency'])) {
                $this->total['Amount'] += $total['Amount'];
                $this->total['Currency'] = $total['Currency'];
            }
        }
        $pos = mb_strpos($text, $this->t('ВНИМАНИЕ!'));

        if (empty($pos)) {
            $pos = mb_strpos($text, 'ATTENTION!');
        }

        if (!empty($pos)) {
            $text = mb_substr($text, 0, $pos);
        }

        $flights = $this->split("#(\n.*\s+\/\s+Check-in number)#", $text);

        foreach ($flights as $flight) {
            if (preg_match("#\s+\/\s+Check-in number\s+([A-Z\d]{5,6})#", $flight, $m)) {
                $RecordLocator = $m[1];
            }
            $flight = preg_replace([
                "#(\n\s*" . $this->preg_implode($this->t('Пересадка')) . "\s*\([A-Z]+\),.{0,15}\s*)$#s",
                "#(\n\s*\w+ \/ Return\s*.+\s*)$#su",
                "#(.+\s+\/\s+Check-in number\s+([A-Z\d]{5,6}).*\n+)#",
            ], '', $flight);
            $seg = [];
            $pos = mb_strpos($flight, 'Landing');
            $depInfo = $this->splitCols(preg_replace("#^\s*\n#", "", mb_substr($flight, 0, $pos)));
            $arrInfo = $this->splitCols(preg_replace("#^\s*\n#", "", mb_substr($flight, $pos)));

            if (count($depInfo) < 3 || count($arrInfo) < 3) {
                $this->http->log("incorrect depInfo or arrInfo parse");

                return;
            }

            if (preg_match("#(\d+\s*\D+\s*\d+\s*\d+:\d+)\s+(?:([^(]+)\(([A-Z]{3})\)|([A-Z]{3}))\s+([^,]*)(?:,\s*" . $this->preg_implode($this->t('Терминал')) . "\s+(.+))?#", $depInfo[1], $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['DepName'] = trim($m[5]);

                if (!empty($m[2])) {
                    $seg['DepName'] = trim($m[2]) . ', ' . $seg['DepName'];
                }

                if (!empty($m[3])) {
                    $seg['DepCode'] = $m[3];
                }

                if (!empty($m[4])) {
                    $seg['DepCode'] = $m[4];
                }

                if (!empty($m[6])) {
                    $seg['DepartureTerminal'] = $m[6];
                }
            }

            if (preg_match("#(\d+\s*\D+\s*\d+\s*\d+:\d+)\s+(?:([^(]+)\(([A-Z]{3})\)|([A-Z]{3}))\s+([^,]*)(?:,\s*" . $this->preg_implode($this->t('Терминал')) . "\s+(.+))?#", $arrInfo[1], $m)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['ArrName'] = trim($m[5]);

                if (!empty($m[2])) {
                    $seg['ArrName'] = trim($m[2]) . ', ' . $seg['ArrName'];
                }

                if (!empty($m[3])) {
                    $seg['ArrCode'] = $m[3];
                }

                if (!empty($m[4])) {
                    $seg['ArrCode'] = $m[4];
                }

                if (!empty($m[6])) {
                    $seg['ArrivalTerminal'] = $m[6];
                }
            }

            if (preg_match("#([\s\S]*)\s+([A-Z\d]{2})-(\d{1,5})(?:\s*\|\s*([\s\S]+))?\n(\S[\s\S]+(?:\s*\/\s*\S[\s\S]+)?)\s*$#", $depInfo[2], $m)) {
                $seg['Operator'] = trim(str_replace("\n", ' ', $m[1]));
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];

                if (!empty($m[4])) {
                    $seg['Aircraft'] = trim(str_replace("\n", ' ', $m[4]));
                }
                $seg['Cabin'] = trim(str_replace("\n", ' ', $m[5]));
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                continue;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded === false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        foreach ($its as $key => $it) {
            foreach ($it['TripSegments'] as $i => $value) {
                unset($its[$key]['TripSegments'][$i]['flightName']);
            }

            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_values(array_unique($its[$key]['Passengers']));
            }

            if (isset($its[$key]['TicketNumbers'])) {
                $its[$key]['TicketNumbers'] = array_values(array_unique($its[$key]['TicketNumbers']));
            }
        }

        return true;
    }

    private function parseHtml_type1(&$its)
    {
        $TripNumber = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Код бронирования:')) . "]/following::text()[string-length(normalize-space())>0][1])[1]", null, true, "#[A-Z\d]+#");

        if ($this->emailType === 'eTicket' || !empty($this->http->FindsingleNode("(//img[contains(@src,'_user-')]/ancestor::tr[1])[1]/preceding-sibling::tr[last()][" . $this->contains($this->t('Онлайн-регистрация')) . "]"))) {
            $RecordLocatorStr = $this->http->FindNodes("(//img[contains(@src,'_user-')]/ancestor::td[1]/following-sibling::td[3])[1]//text()");

            if (empty($RecordLocatorStr)) {
                $RecordLocatorStr = $this->http->FindNodes("(//img[contains(@src,'_user-')]/ancestor::td[1]/following-sibling::td[2])[1]//text()");
            }
            $RecordLocatorStr = preg_replace("#\n{2,}#", "\n", implode("\n", $RecordLocatorStr));

            if (preg_match("#(?:" . $this->t('Вылет') . ":\s*([\s\S]+))?(?:\s*" . $this->t('Обратно') . ":\s*([\s\S]+))?$#uU", $RecordLocatorStr, $m)) {
                if (!empty($m[1])) {
                    if (preg_match_all("#(?:([A-Z\d]{5,7})(?:\s*\(((?:[A-Z]{3}\s*-\s*[A-Z]{3},?\s*)*)\))?)+#", $m[1], $mat)) {
                        foreach ($mat[0] as $key => $value) {
                            if (preg_match_all("#(?:([A-Z]{3})\s*-\s*([A-Z]{3}))#", $mat[2][$key], $match)) {
                                foreach ($match[0] as $i => $val) {
                                    $RecordLocatorAll['DEPARTURE'][$match[1][$i] . '-' . $match[2][$i]] = $mat[1][$key];
                                }
                            } else {
                                $RecordLocatorAll['DEPARTURE']['default'] = $mat[1][$key];
                            }
                        }
                    }
                }

                if (!empty($m[2])) {
                    if (preg_match_all("#(?:([A-Z\d]{5,7})(?:\s*\(((?:[A-Z]{3}\s*-\s*[A-Z]{3},?\s*)*)\))?)+#", $m[2], $mat)) {
                        foreach ($mat[0] as $key => $value) {
                            if (preg_match_all("#(?:([A-Z]{3})\s*-\s*([A-Z]{3}))#", $mat[2][$key], $match)) {
                                foreach ($match[0] as $i => $val) {
                                    $RecordLocatorAll['RETURN'][$match[1][$i] . '-' . $match[2][$i]] = $mat[1][$key];
                                }
                            } else {
                                $RecordLocatorAll['RETURN']['default'] = $mat[1][$key];
                            }
                        }
                    }
                }
            }
        }

        if ($this->emailType === 'eTicket' || !empty($this->http->FindsingleNode("(//img[contains(@src,'_user-')]/ancestor::tr[1])[1]/preceding-sibling::tr[last()][contains(.,'" . $this->t("Электронные билеты") . "')]"))) {
            $TicketNumbersStr = $this->http->FindNodes("//img[contains(@src,'_user-')]/ancestor::td[1]/following-sibling::td[2]");

            foreach ($TicketNumbersStr as $value) {
                if (preg_match_all("#\b([\d\-]{10,})\b(?:\s*\(([A-Z,\s\-]+)\))?#", $value, $m)) {
                    foreach ($m[0] as $key => $value) {
                        if (!empty($m[2][$key])) {
                            if (preg_match_all("#([A-Z]{3})\s*-\s*([A-Z]{3})#", $m[2][$key], $mat)) {
                                foreach ($mat[0] as $i => $val) {
                                    $TicketNumbersAll[$mat[1][$i] . '-' . $mat[2][$i]][] = $m[1][$key];
                                }
                            }
                        } else {
                            $TicketNumbersAll['default'][] = $m[1][$key];
                        }
                    }
                }
            }
        }

        $Passengers = $this->http->FindNodes("//img[contains(@src,'_user-')]/ancestor::td[1]/following-sibling::td[1]");
        $xpath = "//img[contains(@src,'airline_logos_small')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $depInfo = implode("\n", $this->http->FindNodes("./td[2]//text()", $root));

            if (preg_match("#\s*(\D+\d+\/\d+\s+\d+:\d+)\s+(?:(.+[^\(])?\(?([A-Z]{3})\)?)\s*\n+\s*(?:" . $this->preg_implode($this->t('Терминал')) . "(.*)\n)?\s*(.+)#u", $depInfo, $m)) {
                $seg['DepDate'] = $this->calculateDate($m[1]);
                $seg['DepName'] = trim($m[5]);

                if (!empty($m[2])) {
                    $seg['DepName'] = trim($m[2]) . ', ' . $seg['DepName'];
                }

                if (!empty($m[3])) {
                    $seg['DepCode'] = $m[3];
                }

                if (!empty($m[4])) {
                    $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[4]));
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./td[4]//text()", $root));

            if (preg_match("#\s*(\D+\d+\/\d+\s+\d+:\d+)\s+(?:(.+[^\(])?\(?([A-Z]{3})\)?)\s*\n+\s*(?:" . $this->preg_implode($this->t('Терминал')) . "(.*)\n)?\s*(.+)#u", $arrInfo, $m)) {
                $seg['ArrDate'] = $this->calculateDate($m[1]);
                $seg['ArrName'] = trim($m[5]);

                if (!empty($m[2])) {
                    $seg['ArrName'] = trim($m[2]) . ', ' . $seg['ArrName'];
                }

                if (!empty($m[3])) {
                    $seg['ArrCode'] = $m[3];
                }

                if (!empty($m[4])) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[4]));
                }
            }
            $flightInfo = explode(" | ", $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root));

            if (preg_match("#" . $this->t('Рейс') . "\s*([A-Z\d]{2})-(\d{1,5})#u", $flightInfo[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (count($flightInfo) >= 3) {
                if (preg_match("#" . $this->t('Рейс выполняется авиакомпанией') . "\s+(.+)#u", $flightInfo[2], $m)) {
                    $seg['Aircraft'] = trim($flightInfo[1]);
                    $seg['Operator'] = trim($m[1]);
                    $seg['Cabin'] = $flightInfo[3];
                } elseif (preg_match("#" . $this->t('Рейс выполняется авиакомпанией') . "\s+(.+)#u", $flightInfo[1], $m)) {
                    $seg['Operator'] = trim($m[1]);
                    $seg['Cabin'] = $flightInfo[2];
                } else {
                    $seg['Aircraft'] = $flightInfo[1];
                    $seg['Cabin'] = $flightInfo[2];
                }
            } else {
                $seg['Cabin'] = $flightInfo[1];
            }

            if (isset($seg['ArrCode']) && isset($seg['DepCode'])) {
                $seats = array_unique(array_filter($this->http->FindNodes("//img[contains(@src,'icons/seat')]/ancestor::tr[1]//tr[not(.//tr) and contains(., '" . $seg['DepCode'] . "') and contains(., '" . $seg['ArrCode'] . "')]")));

                foreach ($seats as $seat) {
                    if (preg_match("#" . $seg['DepCode'] . "\s*-\s*" . $seg['ArrCode'] . "\s*(\d{1,3}[A-Z])\b#", $seat, $m)) {
                        $seg['Seats'][] = $m[1];
                    }
                }

                if (!empty($RecordLocatorAll)) {
                    $mainInfo = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::table[1]", $root);

                    if (stripos($mainInfo, $this->t('ОТПРАВЛЕНИЕ')) !== false) {
                        if (isset($RecordLocatorAll['DEPARTURE'][$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                            $RecordLocator = $RecordLocatorAll['DEPARTURE'][$seg['DepCode'] . '-' . $seg['ArrCode']];
                        } elseif (isset($RecordLocatorAll['DEPARTURE']['default'])) {
                            $RecordLocator = $RecordLocatorAll['DEPARTURE']['default'];
                        }
                    }

                    if (stripos($mainInfo, $this->t('ВОЗВРАЩЕНИЕ')) !== false) {
                        if (isset($RecordLocatorAll['RETURN'][$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                            $RecordLocator = $RecordLocatorAll['RETURN'][$seg['DepCode'] . '-' . $seg['ArrCode']];
                        } elseif (isset($RecordLocatorAll['RETURN']['default'])) {
                            $RecordLocator = $RecordLocatorAll['RETURN']['default'];
                        }
                    }
                }

                if (empty($RecordLocator)) {
                    $RecordLocator = $TripNumber;
                }

                if (!empty($TicketNumbersAll[$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                    $TicketNumbers = $TicketNumbersAll[$seg['DepCode'] . '-' . $seg['ArrCode']];
                } elseif (!empty($TicketNumbersAll['default'])) {
                    $TicketNumbers = $TicketNumbersAll['default'];
                }
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                continue;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $TicketNumbers);
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && isset($value['AirlineName']) && $seg['AirlineName'] === $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $finded2 = true;
                        }
                    }

                    if ($finded2 === false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'] = $TicketNumbers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space(.) = '" . $this->t('Итого') . "']/following::text()[string-length(normalize-space(.))>0][1]");
        $this->setTotal($total);

        foreach ($its as $key => $it) {
            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_values(array_unique($its[$key]['Passengers']));
            }

            if (isset($its[$key]['TicketNumbers'])) {
                $its[$key]['TicketNumbers'] = array_values(array_unique($its[$key]['TicketNumbers']));
            }
        }

        return true;
    }

    private function parseHtml_type2(&$its)
    {
        $TripNumber = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Код бронирования:')) . "][1]", null, true, "#:\s*([A-Z\d]{5,6})#");

        if ($this->emailType == 'eTicket') {
            $RecordLocatorStr = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(.),'" . $this->t('Пассажиры и их билеты') . "')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//text()"));
            $RecordLocatorStr = preg_replace("#\n+#", "\n", $RecordLocatorStr);

            if (preg_match("#(?:" . $this->t('Вылет') . "\s*\n.+\n([\s\S]+))?(?:\s*" . $this->t('Обратно') . "\s*\n.+\n([\s\S]+))?$#uU", $RecordLocatorStr, $m)) {
                if (!empty($m[1])) {
                    if (preg_match_all("#(?:((?:[A-Z]{3}\s*-\s*[A-Z]{3},?\s*)*)([A-Z\d]{5,6}))+#", $m[1], $mat)) {
                        foreach ($mat[0] as $key => $value) {
                            if (preg_match_all("#(?:([A-Z]{3})\s*-\s*([A-Z]{3}))#", $mat[1][$key], $match)) {
                                foreach ($match[0] as $i => $val) {
                                    $RecordLocatorAll['DEPARTURE'][$match[1][$i] . '-' . $match[2][$i]] = $mat[2][$key];
                                }
                            } else {
                                $RecordLocatorAll['DEPARTURE']['default'] = $mat[2][$key];
                            }
                        }
                    }
                }

                if (!empty($m[2])) {
                    if (preg_match_all("#(?:((?:[A-Z]{3}\s*-\s*[A-Z]{3},?\s*)*)([A-Z\d]{5,6}))+#", $m[2], $mat)) {
                        foreach ($mat[0] as $key => $value) {
                            if (preg_match_all("#(?:([A-Z]{3})\s*-\s*([A-Z]{3}))#", $mat[1][$key], $match)) {
                                foreach ($match[0] as $i => $val) {
                                    $RecordLocatorAll['RETURN'][$match[1][$i] . '-' . $match[2][$i]] = $mat[2][$key];
                                }
                            } else {
                                $RecordLocatorAll['RETURN']['default'] = $mat[2][$key];
                            }
                        }
                    }
                }
            }

            $TicketNumbersStr = array_filter($this->http->FindNodes("(//*[" . $this->contains($this->t('Фамилия - Имя - Обращение'), 'text()') . "])[1]/ancestor::td[1]/*", null, "#[\d- ]{10,}.*#"));

            foreach ($TicketNumbersStr as $value) {
                if (preg_match_all("#\b([\d\-]{10,})\b(?:\s*\(([A-Z,\s\-]+)\))?#", $value, $m)) {
                    foreach ($m[0] as $key => $value) {
                        if (!empty($m[2][$key])) {
                            if (preg_match_all("#([A-Z]{3})\s*-\s*([A-Z]{3})#", $m[2][$key], $mat)) {
                                foreach ($mat[0] as $i => $val) {
                                    $TicketNumbersAll[$mat[1][$i] . '-' . $mat[2][$i]][] = $m[1][$key];
                                }
                            }
                        } else {
                            $TicketNumbersAll['default'][] = $m[1][$key];
                        }
                    }
                }
            }
        }
        $Passengers = $this->http->FindNodes("//*[" . $this->contains($this->t('Фамилия - Имя - Обращение'), 'text()') . "]/following::*[1]");

        $xpath = "//img[contains(@src,'airline_logos_small')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];

            $mainInfo = $this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::table[1]", $root);

            if (preg_match("#\s*(\w+,\s*\d+\s*\w+.?)\s*-\s*\d+:\d+#u", $mainInfo, $m)) {
                $date = $this->calculateDate($m[1]);
            }

            $flight = $this->http->FindSingleNode(".", $root);

            if (preg_match("#" . $this->t('Рейс') . "\s*([A-Z\d]{2})-(\d{1,5})#u", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $depInfo = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

            if (preg_match("#\s*\D+\s+(\d+:\d+)\s+([A-Z]{3}),\s*(.+)(?:\s*-\s*" . $this->preg_implode($this->t('Терминал')) . "\s*(.+))?\s*$#uU", $depInfo, $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['DepCode'] = $m[2];

                $seg['DepName'] = trim($m[3]);

                if (!empty($m[4])) {
                    $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[4]));
                }
            }

            $arrInfo = $this->http->FindSingleNode("./following-sibling::tr[2]", $root);

            if (preg_match("#\s*\D+\s+(\d+:\d+)\s+([A-Z]{3}),\s*(.+)(?:\s*-\s*" . $this->preg_implode($this->t('Терминал')) . "\s*(.+))?\s*$#uU", $arrInfo, $m)) {
                $seg['ArrDate'] = strtotime($m[1], $date);
                $seg['ArrName'] = trim($m[3]);
                $seg['ArrCode'] = $m[2];

                if (!empty($m[4])) {
                    $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $m[4]));
                }
            }

            if (!empty($RecordLocatorAll)) {
                if (stripos($mainInfo, $this->t('Вылет')) !== false) {
                    if (isset($RecordLocatorAll['DEPARTURE'][$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                        $RecordLocator = $RecordLocatorAll['DEPARTURE'][$seg['DepCode'] . '-' . $seg['ArrCode']];
                    } elseif (isset($RecordLocatorAll['DEPARTURE']['default'])) {
                        $RecordLocator = $RecordLocatorAll['DEPARTURE']['default'];
                    }
                }

                if (stripos($mainInfo, $this->t('Обратно')) !== false) {
                    if (isset($RecordLocatorAll['RETURN'][$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                        $RecordLocator = $RecordLocatorAll['RETURN'][$seg['DepCode'] . '-' . $seg['ArrCode']];
                    } elseif (isset($RecordLocatorAll['RETURN']['default'])) {
                        $RecordLocator = $RecordLocatorAll['RETURN']['default'];
                    }
                }
            }

            if (empty($RecordLocator)) {
                $RecordLocator = $TripNumber;
            }

            $flightInfo = explode(" | ", $this->http->FindSingleNode("./following-sibling::tr[3]", $root));
            $seg['Cabin'] = $flightInfo[0];

            if (!empty($flightInfo[1])) {
                $seg['Aircraft'] = $flightInfo[1];
            }

            if (isset($flightInfo[2]) && preg_match("#" . $this->t('Рейс выполняется авиакомпанией') . "\s+(.+)#u", $flightInfo[2], $m)) {
                $seg['Operator'] = trim($m[1]);
            }

            if (isset($seg['ArrCode']) && isset($seg['DepCode'])) {
                if (!empty($TicketNumbersAll[$seg['DepCode'] . '-' . $seg['ArrCode']])) {
                    $TicketNumbers = $TicketNumbersAll[$seg['DepCode'] . '-' . $seg['ArrCode']];
                } elseif (!empty($TicketNumbersAll['default'])) {
                    $TicketNumbers = $TicketNumbersAll['default'];
                }
            }

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                continue;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] === $RecordLocator) {
                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $TicketNumbers);
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && isset($value['AirlineName']) && $seg['AirlineName'] === $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] === $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] === $value['DepDate']) {
                            $finded2 = true;
                        }
                    }

                    if ($finded2 === false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded === false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'] = $TicketNumbers;
                }

                if (isset($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space(.) = '" . $this->t('Итого') . ":']/following::text()[string-length(normalize-space())>0][1]");
        $this->setTotal($total);

        foreach ($its as $key => $it) {
            if (isset($its[$key]['Passengers'])) {
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
            }

            if (isset($its[$key]['TicketNumbers'])) {
                $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
            }
        }

        return true;
    }

    private function assignLangHtml(): bool
    {
        foreach ($this->langDetectorsHtml as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangPdf($text): bool
    {
        foreach ($this->langDetectorsPdf as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
            "#^\s*(\d{1,2})\s*(\w+)\.?\s*(\d{4})\s*(\d+:\d+)\s*$#u", // 30 нояб. 2016 03:05
        ];
        $out = [
            "$1 $2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, 0, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function strposArr($text, $fields)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            if (strpos($text, $field) !== false) {
                return true;
            }
        }

        return false;
    }
}
