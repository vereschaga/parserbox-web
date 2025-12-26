<?php

namespace AwardWallet\Engine\gotogate\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOrder extends \TAccountChecker
{
    public $mailFiles = "gotogate/it-647172240.eml, gotogate/it-647183759.eml, gotogate/it-647989001.eml, gotogate/it-654684648.eml, gotogate/it-655087004.eml, gotogate/it-656275222.eml, gotogate/it-659507233.eml, gotogate/it-659908378.eml, gotogate/it-660500006.eml, gotogate/it-660752446.eml, gotogate/it-663328792.eml, gotogate/it-712952270.eml, gotogate/it-715930762.eml, gotogate/it-775775694.eml, gotogate/it-806883172.eml, gotogate/it-807161711.eml, gotogate/it-814327055.eml, gotogate/it-814985605.eml, gotogate/it-820306884.eml, gotogate/it-821700933.eml, gotogate/it-821768273.eml, gotogate/it-823450538.eml, gotogate/it-826068091.eml";
    public $subjects = [
        // en
        'Your trip is confirmed. Order number',
        // pt
        'Sua viagem está confirmada. Número do pedido',
        'A sua viagem está confirmada. Número de pedido',
        // fr
        'Votre voyage est confirmé. Numéro de commande',
        // it
        'Il tuo viaggio è confermato Numero d’ordine',
        // da
        'Din rejse er bekræftet. Ordrenummer',
        // nl
        'Je reis is bevestigd. Bestelnummer',
        // de
        'Ihre Reise ist bestätigt. Auftragsnummer',
        // es
        'Su viaje está confirmado. Número de pedido',
        'Tu viaje está confirmado. Número de pedido',
        // sv
        'Din resa är bekräftad. Bokningsnummer',
        // uk
        'Вашу подорож підтверджено. Номер замовлення',
        // ko
        '여행이 확정되었습니다. 주문 번호',
        // hu
        'Utazása visszaigazolása megtörtént. Rendelési szám:',
        // ru
        'Ваша поездка подтверждена. Номер заказа',
        // pl
        'Twoja podróż została potwierdzona. Zamówienie numer',
        // zh
        '您的行程已确认。订单编号',
        // bg
        'Пътуването ви е потвърдено. Номер на поръчката',
        // fi
        'Matkasi on vahvistettu. Tilausnumero',
        // no
        'Reisen din er bekreftet. Bestillingsnummer',
        //ja
        'ご旅行が確定しました。ご予約番号',
        // cs
        'Vaše cesta je potvrzena. Číslo objednávky',
        // ro
        'Călătoria dumneavoastră este confirmată. Comanda cu numărul',
        // th
        'ยืนยันการเดินทางของคุณแล้ว หมายเลขคำสั่งซื้อ',
        // el
        'Το ταξίδι σας επιβεβαιώθηκε. Αριθμός παραγγελίας',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            // Html
            'Order Status'      => 'Order Status',
            'Order number'      => ['Order number', 'Order Number'],
            'Your Trip Summary' => 'Your Trip Summary',
            'Stop(s)'           => ['Stop(s)', 'stops'],
            // 'First name' => '',
            'Last name' => ['Last name', 'Surname'],
            'Total'     => ['Sum', 'Total'],

            // Pdf
            'Order number:'    => ['Order number:', 'ORDER NUMBER:'],
            'Payment Overview' => ['Payment Overview', 'Payment overview'],
            // 'Trip' => '',
            // 'Description' => '',
            // 'Price' => '',
            // 'Taxes & charges' => '',
            // 'Seat reservations' => '',
            'Additions Fee' => ['Seat reservations', 'Checked baggage*', 'Trip Cancellation Protection', 'Hand baggage*', 'Baggage service',
                'Flexible Ticket', ],
            'Sum'           => ['Total', 'Sum'],
            // 'Total amount' => '',
            // 'Additional Products:' => '',
        ],
        "pt" => [
            // Html
            'Order Status'      => ['Status do pedido', 'Estado da encomenda'],
            'Order number'      => ['Número do pedido', 'Número de pedido'],
            'Your Trip Summary' => ['Resumo de sua viagem', 'Resumo da viagem'],
            'Stop(s)'           => ['Parada(s)', 'Paragem(ns)', 'paradas', 'escalas'],
            'First name'        => ['Nome', 'Nome próprio'],
            'Last name'         => ['Sobrenome'],
            'Total'             => ['Total', 'Soma'],

            // Pdf
            'Order number:'        => ['Número do pedido:', 'Número de pedido:'],
            'Payment Overview'     => ['Informações de Pagamento', 'Descrição geral do pagamento'],
            'Trip'                 => 'Viagem',
            'Description'          => 'Descrição',
            'Price'                => 'Preço',
            'Taxes & charges'      => ['Impostos e taxas', 'Taxas e encargos'],
            'Seat reservations'    => 'Reserva de assento (Impostos incluídos)',
            'Additions Fee'        => ['Reserva de assento (Impostos incluídos)', 'Bagagem despachada (Impostos incluídos)*'],
            'Sum'                  => ['Total', 'Soma'],
            'Total amount'         => ['Valor total', 'Montante total'],
            'Additional Products:' => 'Produtos adicionais:',
        ],
        "it" => [
            // Html
            'Order Status'      => 'Stato dell’ordine',
            'Order number'      => 'Numero ordine',
            'Your Trip Summary' => 'Riepilogo del tuo viaggio',
            'Stop(s)'           => ['Scalo(i):', 'scali'],
            'First name'        => 'Nome',
            'Last name'         => ['Cognome'],
            'Total'             => 'Somma',

            // Pdf
            'Order number:'        => 'Numero ordine:',
            'Payment Overview'     => 'Panoramica sul pagamento',
            'Trip'                 => 'Viaggio',
            'Description'          => 'Descrizione',
            'Price'                => 'Prezzo',
            'Taxes & charges'      => 'Tasse e spese',
            'Seat reservations'    => 'Prenotazione del posto',
            'Additions Fee'        => ['Prenotazione del posto', 'Copertura Fallimento Compagnia Aerea', 'Bagaglio da imbarcare*'],
            'Sum'                  => ['Somma'],
            'Total amount'         => 'Importo totale',
            'Additional Products:' => 'Prodotti aggiuntivi:',
        ],
        "da" => [
            // Html
            'Order Status'      => 'Ordrestatus',
            'Order number'      => 'Ordrenummer',
            'Your Trip Summary' => 'Din rejseoversigt',
            'Stop(s)'           => ['Stop', 'Skift'],
            'First name'        => 'Fornavn',
            'Last name'         => 'Efternavn',
            'Total'             => 'Sum',

            // Pdf
            'Order number:'        => 'Ordrenummer:',
            'Payment Overview'     => 'Betalingsoversigt',
            'Trip'                 => 'Rejse',
            'Description'          => 'Beskrivelse',
            'Price'                => 'pris',
            'Taxes & charges'      => 'Skatter & afgifter',
            'Seat reservations'    => ['Platsreservation', 'Pladsreservation'],
            'Additions Fee'        => ['Platsreservation', 'Handbagage*', 'Ombokningsbar biljett', 'Pladsreservation'],
            'Sum'                  => ['Summa'],
            'Total amount'         => 'I alt',
            // 'Additional Products:' => ':',
        ],
        "fr" => [
            // Html
            'Order Status'      => 'Statut de la commande',
            'Order number'      => 'Numéro de commande',
            'Your Trip Summary' => 'Récapitulatif de votre voyage',
            'Stop(s)'           => ['Escale(s)', 'escales'],
            'First name'        => 'Prénom du titulaire',
            'Last name'         => 'Nom du titulaire',
            'Total'             => 'Total',

            // Pdf
            'Order number:'        => 'Numéro de commande:',
            'Payment Overview'     => 'Aperçu du paiement',
            'Trip'                 => 'Voyage',
            'Description'          => 'Description',
            'Price'                => 'Prix',
            'Taxes & charges'      => 'Taxes et frais',
            'Seat reservations'    => 'Réservation de sièges',
            'Additions Fee'        => ['Bagage en soute*', 'Bagage à main*', 'Réservation de sièges', 'Informations relatives au vol par SMS',
                'Billet flexible', ],
            'Sum'                  => ['Total'],
            'Total amount'         => 'Montant total',
            'Additional Products:' => 'Produits complémentaires:',
        ],
        "nl" => [
            // Html
            'Order Status'      => 'Status van de bestelling',
            'Order number'      => 'Ordernummer',
            'Your Trip Summary' => 'Overzicht van je reis',
            'Stop(s)'           => ['Stop(s)', 'tussenstops'],
            'First name'        => 'Voornaam',
            'Last name'         => 'Achternaam',
            'Total'             => 'Eindtotaal',

            // Pdf
            'Order number:'        => 'Ordernummer:',
            'Payment Overview'     => 'Betalingsoverzicht',
            'Trip'                 => 'Reis',
            'Description'          => 'Beschrijving',
            'Price'                => 'Prijs',
            'Taxes & charges'      => 'Belastingen en toeslagen',
            'Seat reservations'    => 'Zitplaatsreserveringen',
            'Additions Fee'        => ['Zitplaatsreserveringen', 'Ruimbagage*'],
            'Sum'                  => ['Bedrag'],
            'Total amount'         => 'Totaalbedrag',
            // 'Additional Products:' => ':',
        ],
        "de" => [
            // Html
            'Order Status'      => 'Status der Bestellung',
            'Order number'      => 'Buchungsnummer',
            'Your Trip Summary' => 'Zusammenfassung Ihrer Reise',
            'Stop(s)'           => ['Zwischenstopp(s)', 'stopps'],
            'First name'        => 'Vorname',
            'Last name'         => 'Nachname',
            'Total'             => 'Summe',

            // Pdf
            'Order number:'        => 'Buchungsnummer:',
            'Payment Overview'     => 'Preisspezifikation',
            'Trip'                 => 'Reise',
            'Description'          => 'Beschreibung',
            'Price'                => 'Preis',
            'Taxes & charges'      => 'Steuern und Gebühren',
            'Seat reservations'    => 'Sitzplatzreservierungen',
            'Additions Fee'        => ['Aufgegebenes Gepäck*', 'Aktualisierungen zu Flügen per SMS', 'Buchungsnummer per SMS', 'Sitzplatzreservierungen',
                'Handgepäck*', ],
            'Sum'                  => ['Summe'],
            'Total amount'         => 'Gesamtsumme',
            'Additional Products:' => 'Zusätzliche Produkte:',
        ],
        "es" => [
            // Html
            'Order Status'      => 'Estado del pedido',
            'Order number'      => ['Número de reserva', 'Número de pedido'],
            'Your Trip Summary' => ['Resumen de su viaje', 'Resumen de tu viaje'],
            'Stop(s)'           => ['Escala(s)', 'Escala/s', 'Cambios'],
            'First name'        => 'Nombre',
            'Last name'         => ['Apellidos', 'Apellido'],
            'Total'             => 'Total',

            // Pdf
            'Order number:'        => ['Número de reserva:', 'Número de pedido:'],
            'Payment Overview'     => ['Resumen del pago', 'Descripción general del pago'],
            'Trip'                 => 'Viaje',
            'Description'          => 'Descripción',
            'Price'                => 'Precio',
            'Taxes & charges'      => ['Impuestos y recargos', 'Impuestos y cargos'],
            'Seat reservations'    => ['Reservas de asientos', 'Reserva de asiento'],
            'Additions Fee'        => ['Reservas de asientos', 'Equipaje de mano*', 'Actualizaciones de vuelos por SMS', 'Equipaje facturado*',
                'AirHelp Plus Compensación por vuelos demorados', 'Boleto flexible', 'Reserva de asiento', 'Referencia de tu reserva por SMS', ],
            'Sum'                  => ['Total'],
            'Total amount'         => 'Importe total',
            'Additional Products:' => 'Productos adicionales:',
        ],
        "sv" => [
            // Html
            'Order Status'      => 'Bokningsstatus',
            'Order number'      => 'Ordernummer',
            'Your Trip Summary' => 'Sammanfattning av din resa',
            'Stop(s)'           => ['Mellanlandning(ar)', 'byten'],
            'First name'        => 'Förnamn',
            'Last name'         => 'Efternamn',
            'Total'             => 'Summa',

            // Pdf
            'Order number:'        => 'Ordernummer:',
            'Payment Overview'     => 'Prisspecifikation',
            'Trip'                 => 'Resa',
            'Description'          => 'Beskrivning',
            'Price'                => 'Pris',
            'Taxes & charges'      => 'Flygskatter',
            // 'Seat reservations'    => '',
            'Additions Fee'        => ['Handbagage*', 'Incheckat bagage*', 'Förseningskompensation AirHelp Plus',
            ],
            'Sum'                  => ['Summa'],
            'Total amount'         => 'Totalt',
            'Additional Products:' => 'Ytterligare produkter:',
        ],
        "uk" => [
            // Html
            'Order Status'      => 'Статус замовлення',
            'Order number'      => 'Номер замовлення',
            'Your Trip Summary' => 'Деталі вашої подорожі',
            'Stop(s)'           => 'Зупинки',
            'First name'        => 'Ім’я',
            'Last name'         => 'Прізвище',
            'Total'             => 'Сума',

            // Pdf
            'Order number:'        => 'Номер замовлення:',
            'Payment Overview'     => 'Огляд платежу',
            'Trip'                 => 'Подорож',
            'Description'          => 'Опис',
            'Price'                => 'Ціна',
            'Taxes & charges'      => 'Податки та комісії',
            // 'Seat reservations'    => '',
            'Additions Fee'        => ['Зареєстрований багаж'],
            'Sum'                  => ['Сума'],
            'Total amount'         => 'Загальна сума',
            // 'Additional Products:' => ':',
        ],
        "ko" => [
            // Html
            'Order Status'      => '주문 상태',
            'Order number'      => '주문 번호',
            'Your Trip Summary' => '여행 요약',
            'Stop(s)'           => ['기착', '환승'],
            'First name'        => '영문 이름',
            'Last name'         => '영문 성',
            'Total'             => '합계',

            // Pdf
            'Order number:'        => '주문 번호:',
            'Payment Overview'     => '결제 정보 요약',
            'Trip'                 => '여행',
            'Description'          => '설명',
            'Price'                => '가격',
            'Taxes & charges'      => '세금 및 수수료',
            'Seat reservations'    => '좌석 예약',
            'Additions Fee'        => ['좌석 예약', '수하물 서비스'],
            'Sum'                  => ['합계'],
            'Total amount'         => '총계',
            'Additional Products:' => '추가 상품:',
        ],
        "hu" => [
            // Html
            'Order Status'      => 'Rendelési állapot',
            'Order number'      => 'Rendelési szám',
            'Your Trip Summary' => 'Az utazás összegzése',
            'Stop(s)'           => 'Közbenső állomás(ok)',
            'First name'        => 'Keresztnév',
            'Last name'         => 'Vezetéknév',
            'Total'             => 'Összeg',

            // Pdf
            'Order number:'        => 'Rendelési szám:',
            'Payment Overview'     => 'Fizetés áttekintése',
            'Trip'                 => 'Utazás',
            'Description'          => 'Leírás',
            'Price'                => 'Ár',
            'Taxes & charges'      => 'Adók és díjak',
            // 'Seat reservations'    => '',
            // 'Additions Fee'        => ['', ''],
            'Sum'                  => ['Összeg'],
            'Total amount'         => 'Teljes összeg',
            // 'Additional Products:' => ':',
        ],
        "ru" => [
            // Html
            'Order Status'      => 'Статус заказа',
            'Order number'      => 'Номер заказа',
            'Your Trip Summary' => 'Краткая информация о вашей поездке',
            'Stop(s)'           => 'Пересадки:',
            'First name'        => 'Имя',
            'Last name'         => 'Фамилия',
            'Total'             => 'Сумма',

            // Pdf
            'Order number:'        => 'Номер заказа:',
            'Payment Overview'     => 'Спецификация цены',
            'Trip'                 => 'Поездка',
            'Description'          => 'Описание',
            'Price'                => 'Цена',
            'Taxes & charges'      => 'Taxes & charges',
            // 'Seat reservations'    => '',
            // 'Additions Fee'        => ['', ''],
            'Sum'                  => ['Сумма'],
            'Total amount'         => 'Общая сумма',
            'Additional Products:' => 'Дополнительные продукты:',
        ],
        "pl" => [
            // Html
            'Order Status'      => 'Status zamówienia',
            'Order number'      => 'Numer zamówienia',
            'Your Trip Summary' => 'Podsumowanie podróży',
            'Stop(s)'           => 'Międzylądowania',
            'First name'        => 'Imię (imiona)',
            'Last name'         => 'Nazwisko',
            'Total'             => 'Suma',

            // Pdf
            'Order number:'        => 'Numer zamówienia:',
            'Payment Overview'     => 'Przegląd płatności',
            'Trip'                 => 'Podróż',
            'Description'          => 'Opis',
            'Price'                => 'Cena',
            'Taxes & charges'      => 'Podatki i opłaty',
            // 'Seat reservations'    => '',
            'Additions Fee'        => ['Rekompensata za opóźnienie — AirHelp Plus'],
            'Sum'                  => ['Suma'],
            'Total amount'         => 'Suma łączna',
            'Additional Products:' => 'Produkty dodatkowe:',
        ],
        "zh" => [
            // Html
            'Order Status'      => '订单状态',
            'Order number'      => '订单编号',
            'Your Trip Summary' => '您的行程总结',
            'Stop(s)'           => ['中途停留', '停留'],
            'First name'        => '名',
            'Last name'         => '姓',
            'Total'             => '合计',

            // Pdf
            'Order number:'        => '订单编号:',
            'Payment Overview'     => '付款一览',
            'Trip'                 => '旅行',
            'Description'          => '描述',
            'Price'                => '价格',
            'Taxes & charges'      => '税费和手续费',
            'Seat reservations'    => '座位预订',
            'Additions Fee'        => ['座位预订', '行李服务'],
            'Sum'                  => ['合计'],
            'Total amount'         => '总额',
            'Additional Products:' => '附加产品:',
        ],
        "bg" => [
            // Html
            'Order Status'      => 'Статус на поръчката',
            'Order number'      => 'Номер на поръчка',
            'Your Trip Summary' => 'Резюме на вашето пътуване',
            'Stop(s)'           => 'Спирка(и)',
            'First name'        => 'Първо име',
            'Last name'         => 'Фамилия',
            'Total'             => 'Сума',

            // Pdf
            'Order number:'        => 'Номер на поръчка:',
            'Payment Overview'     => 'Обзор на плащането',
            'Trip'                 => 'Пътуване',
            'Description'          => 'Описание',
            'Price'                => 'Цена',
            'Taxes & charges'      => 'Данъци и такси',
            'Seat reservations'    => 'Резервация на места',
            'Additions Fee'        => ['Защита срещу отмяна', 'Резервация на места'],
            'Sum'                  => ['Сума'],
            'Total amount'         => 'Обща сума',
            // 'Additional Products:' => '附加产品:',
        ],
        "fi" => [
            // Html
            'Order Status'      => 'Tilauksen tila',
            'Order number'      => 'Tilausnumero',
            'Your Trip Summary' => 'Matkasi yhteenveto',
            'Stop(s)'           => ['Välilasku(t)', 'välilaskua'],
            'First name'        => 'Etunimi',
            'Last name'         => 'Sukunimi',
            'Total'             => 'Yhteensä',

            // Pdf
            'Order number:'        => 'Tilausnumero:',
            'Payment Overview'     => 'Maksuerittely',
            'Trip'                 => 'Matka',
            'Description'          => 'Kuvaus',
            'Price'                => 'Hinta',
            'Taxes & charges'      => 'Verot & tullimaksut',
            'Seat reservations'    => 'Paikkavaraus',
            'Additions Fee'        => ['Paikkavaraus'],
            'Sum'                  => ['Yhteensä'],
            'Total amount'         => 'Yhteensä',
            // 'Additional Products:' => '附加产品:',
        ],
        "no" => [
            // Html
            'Order Status'      => 'Bestillingsstatus',
            'Order number'      => 'Ordernummer',
            'Your Trip Summary' => 'Sammenfatning av reisen',
            'Stop(s)'           => ['Stopp', 'Flybytter'],
            'First name'        => 'Fornavn',
            'Last name'         => 'Etternavn',
            'Total'             => 'Sum',

            // Pdf
            'Order number:'        => 'Ordernummer:',
            'Payment Overview'     => 'Betalingsoversikt',
            'Trip'                 => 'Reise',
            'Description'          => 'Beskrivelse',
            'Price'                => 'Pris',
            'Taxes & charges'      => 'Skatter og avgifter',
            // 'Seat reservations'    => 'Paikkavaraus',
            'Additions Fee'        => ['Innsjekket bagasje*'],
            'Sum'                  => ['Summa'],
            'Total amount'         => 'Totalt',
            // 'Additional Products:' => '附加产品:',
        ],
        "ja" => [
            // Html
            'Order Status'      => 'ご予約状況',
            'Order number'      => 'ご予約番号',
            'Your Trip Summary' => 'ご旅行の概要',
            'Stop(s)'           => ['途中着陸', '回'],
            'First name'        => '名',
            'Last name'         => '姓',
            'Total'             => '総額',

            // Pdf
            'Order number:'        => 'ご予約番号:',
            'Payment Overview'     => 'お支払いの概要',
            'Trip'                 => '旅行',
            'Description'          => '説明',
            'Price'                => '金額',
            'Taxes & charges'      => '税金＆料金',
            // 'Seat reservations'    => 'Paikkavaraus',
            // 'Additions Fee'        => ['Paikkavaraus'],
            'Sum'                  => ['合計'],
            'Total amount'         => '合計',
            // 'Additional Products:' => '附加产品:',
        ],
        "cs" => [
            // Html
            'Order Status'      => 'Stav objednávky',
            'Order number'      => 'Číslo objednávky',
            'Your Trip Summary' => 'Shrnutí vaší cesty',
            'Stop(s)'           => ['Zastávka (zastávky)', 'zastávky'],
            'First name'        => 'Křestní jméno',
            'Last name'         => 'Příjmení',
            'Total'             => 'Celkem',

            // Pdf
            'Order number:'        => 'Číslo objednávky:',
            'Payment Overview'     => 'Přehled platby',
            'Trip'                 => 'Cesta',
            'Description'          => 'Popis',
            'Price'                => 'Cena',
            'Taxes & charges'      => 'Daně a poplatky',
            'Seat reservations'    => 'Rezervace sedadel',
            'Additions Fee'        => ['Rezervace sedadel'],
            'Sum'                  => ['Celkem'],
            'Total amount'         => ['Celkem', 'Celková částka'],
            // 'Additional Products:' => '附加产品:',
        ],
        "ro" => [
            // Html
            'Order Status'      => 'Starea comenzii',
            'Order number'      => 'Număr comandă',
            'Your Trip Summary' => 'Rezumatul călătoriei dumneavoastră',
            // 'Stop(s)'           => 'Спирка(и)',
            'First name'        => 'Prenume',
            'Last name'         => 'Nume',
            'Total'             => 'Sumă',

            // Pdf
            'Order number:'        => 'Număr comandă:',
            'Payment Overview'     => 'Prezentarea Plăţii',
            'Trip'                 => 'Călătorie',
            'Description'          => 'Descriere',
            'Price'                => 'Preţ',
            'Taxes & charges'      => 'Taxe & costuri',
            'Seat reservations'    => 'Rezervări de locuri',
            'Additions Fee'        => ['Rezervări de locuri'],
            'Sum'                  => ['Sumă'],
            'Total amount'         => 'Suma totală',
            // 'Additional Products:' => '附加产品:',
        ],
        "th" => [
            // Html
            'Order Status'      => 'สถานะการสั่งซื้อ',
            'Order number'      => 'หมายเลขสั่งซื้อ',
            'Your Trip Summary' => 'ข้อมูลสรุปการเดินทางของคุณ',
            'Stop(s)'           => 'การเปลี่ยนแปลง',
            'First name'        => 'ชื่อ',
            'Last name'         => 'นามสกุล',
            'Total'             => 'จำนวนรวม',

            // Pdf
            'Order number:'        => 'หมายเลขสั่งซื้อ:',
            'Payment Overview'     => 'ภาพรวมการชำระเงิน',
            'Trip'                 => 'การเดินทาง',
            'Description'          => 'คำอธิบาย',
            'Price'                => 'ราคา',
            'Taxes & charges'      => 'ภาษีและค่าบริการ',
            // 'Seat reservations'    => 'Rezervări de locuri',
            // 'Additions Fee'        => ['Rezervări de locuri'],
            'Sum'                  => ['รวม'],
            'Total amount'         => 'ยอดรวม',
            'Additional Products:' => 'ผลิตภัณฑ์เพิ่มเติม:',
        ],
        "el" => [
            // Html
            'Order Status'      => 'Κατάσταση παραγγελίας',
            'Order number'      => 'Αριθμός παραγγελίας',
            'Your Trip Summary' => 'Σύνοψη του ταξιδιού σας',
            'Stop(s)'           => 'Ενδιάμεσοι σταθμοί',
            'First name'        => 'Όνομα',
            'Last name'         => 'Επώνυμο',
            'Total'             => 'Σύνολο',

            // Pdf
            'Order number:'        => 'Αριθμός παραγγελίας:',
            'Payment Overview'     => 'Επισκόπηση πληρωμής',
            'Trip'                 => 'Ταξίδι',
            'Description'          => 'Περιγραφή',
            'Price'                => 'Τιμή',
            'Taxes & charges'      => 'Φόροι και χρεώσεις',
            'Seat reservations'    => 'Κρατήσεις θέσης',
            'Additions Fee'        => ['Κρατήσεις θέσης', 'Κωδικός κράτησης με γραπτό μήνυμα', 'Χειραποσκευές*',
                'Αποσκευές για check-in*', ],
            'Sum'                  => ['Σύνολο'],
            'Total amount'         => 'Συνολικό ποσό',
            'Additional Products:' => 'Πρόσθετα προϊόντα:',
        ],
    ];

    public static $providers = [
        "gotogate" => [
            "from"       => "gotogate.com",
            "detectBody" => [
                'Gotogate!',
                "Thank you for booking with Gotogate", "Obrigado por reservar com a Gotogate!", ],
        ],

        "trip" => [
            "from"       => ".mytrip.com",
            "detectBody" => [
                'Mytrip. ', ' Mytrip!', '@support.kr.mytrip.com',
                "Thank you for booking with Mytrip", 'Obrigado por reservar com a Mytrip!', ],
        ],

        "fnt" => [
            "from"       => "flightnetwork.com",
            "detectBody" => [
                'Flight Network',
            ],
        ],

        "nokair" => [
            "from"       => "@nokgo.nokair.com",
            "detectBody" => [
                ' NOK GO!',
                "Thank you for booking with NOK GO", ],
        ],

        "flybillet" => [
            "from"       => "@support.flybillet.dk",
            "detectBody" => ["fordi du bookede med Flybillet"],
        ],
        "supersaver" => [
            "from"       => "@supersaver.nl",
            "detectBody" => ["@supersaver.nl", 'Supersaver Travel',
                ' Seat24!', ' Supersaver!',
                'Bedankt voor uw boeking bij Supersaver',
            ],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectProvider()
    {
        foreach (self::$providers as $prov => $provider) {
            foreach ($provider['detectBody'] as $provKey) {
                if ($this->http->XPath->query("//text()[{$this->contains($provKey)}]")->length > 0) {
                    return $prov;
                }
            }
        }

        return null;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Order Status']) && !empty($dict['Order number']) && !empty($dict['Your Trip Summary'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Order Status'])}]/following::text()[normalize-space()][1][{$this->eq($dict['Order number'])}]/following::text()[{$this->eq($dict['Your Trip Summary'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'gotogate.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your Trip Summary'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your Trip Summary'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $text = null;

        if (count($pdfs) > 0) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (!$this->containsText($text, $this->t('Payment Overview'))
                || !$this->containsText($text, $this->t('Description'))
                || !$this->containsText($text, $this->t('Total amount'))
            ) {
                $text = '';
            }
        }
        $this->ParseFlight($email, $text);

        $code = $this->detectProvider();

        if (!empty($code)) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email, string $pdfText = null)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Order Status'))}]][*[2][{$this->eq($this->t('Order number'))}]]/following-sibling::tr[1]/td[2]", null, true, "/^\s*([A-Z\d\-]{5,})\s*$/");
        $email->ota()
            ->confirmation($conf);

        if (!preg_match("/\b{$this->opt($this->t('Order number:'))} *{$conf}\s*/u", $pdfText)) {
            $pdfText = null;
            $this->logger->info('pdf receipt for another reservation');
        }

        if (!empty($pdfText)) {
            // удаление номеров страниц
            $pdfText = preg_replace("/ {15,}\d{1,2} ?\/ ?\d{1,2}\n+.+\n+ {15,}{$this->opt($this->t('Order number:'))}.+/u", '', $pdfText);
        }

        // Flight
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Order Status'))}]][*[2][{$this->eq($this->t('Order number'))}]]/following-sibling::tr[1]/td[1]"));

        $travellerNodes = $this->http->XPath->query("//tr[*[normalize-space()][1][{$this->eq($this->t('First name'))}]][*[normalize-space()][2][{$this->eq($this->t('Last name'))}]]/following-sibling::tr");
        $travellers = [];

        foreach ($travellerNodes as $tRoot) {
            if ($this->http->XPath->query("./td[position() = 1 or position() = 3][{$this->eq($this->t('First name'))} or {$this->eq($this->t('Last name'))}]", $tRoot)->length === 0
                && $this->http->XPath->query("./td", $tRoot)->length < 5
            ) {
                $firstName = $this->http->FindSingleNode("./td[1]", $tRoot);
                $lastName = $this->http->FindSingleNode("./td[2]", $tRoot);
            } else {
                $firstName = $this->http->FindSingleNode("./td[2]", $tRoot);
                $lastName = $this->http->FindSingleNode("./td[4]", $tRoot);
            }

            if (!empty($firstName) && !empty($lastName)) {
                $travellers[] = $firstName . ' ' . $lastName;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->contains($this->t(('passenger(s):')))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()]",
            null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+[•]\s+/u");
        }

        $f->general()
            ->travellers($travellers);

        // Price
        $taxTranslates = (array) $this->t('Taxes & charges');

        foreach ($taxTranslates as $i => $tr) {
            $taxTranslates[$i] = str_replace(' ', '(?: ', preg_quote($tr, '/'), $count) . str_repeat(")?", $count);
        }
        $taxHeaderBeforeDescription = "(?:" . implode('|', $taxTranslates) . ")";

        if (!empty($pdfText)
            && preg_match("/\n((?: {20,}{$taxHeaderBeforeDescription}\n+)? *{$this->opt($this->t('Description'))} {3,}{$this->opt($this->t('Price'))} {3,}.+[\s\S]+?\n *{$this->opt($this->t('Total amount'))} {3,}.+)/u", $pdfText, $m)
        ) {
            // delete page number
            $pdfText = preg_replace("/\n {20,}\d{1,2} ?\/ ?\d{1,2}\s*\n(.*\n){0,2} *Gotogate Pty Ltd,.+/", '', $pdfText);
            // Парсинг цены из пдф файла
            $priceText = $m[1];
            // $this->logger->debug('$priceText = '."\n".print_r( $priceText,true));
            $currency = null;

            if (preg_match("/\n *{$this->opt($this->t('Total amount'))} {5,}(?<total>\d[\d\.\, \'\’]*?) *(?<currency>[A-Z]{3})\s*$/u", $priceText, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
                $currency = $m['currency'];

                $priceTable = $this->createTable($priceText, $this->rowColumnPositions($this->inOneRow($priceText)));

                if (preg_match("/^\s*{$this->opt($this->t('Description'))}\s*\n/u", $priceTable[0] ?? '')
                    && preg_match("/^\s*{$this->opt($this->t('Price'))}\s*\n/u", $priceTable[2] ?? '')
                ) {
                    unset($priceTable[1]);
                    $priceTable = array_values($priceTable);
                }

                if (preg_match("/^\s*{$this->opt($this->t('Price'))}\s*\n/u", $priceTable[1] ?? '')) {
                    $rows = array_filter(explode("\n", $priceTable[1]));
                    array_shift($rows);
                    $cost = 0.0;

                    foreach ($rows as $row) {
                        if (preg_match("/^\s*(?<total>\d[\d\.\, \'\’]*?) *(?<currency>{$currency})\s*$/", $row, $m)) {
                            $cost += PriceHelper::parse($m['total'], $currency);
                        } else {
                            $cost = 0.0;

                            break;
                        }
                    }

                    if (!empty($cost)) {
                        $f->price()
                            ->cost($cost);
                    }
                }

                if (preg_match("/^\s*" . str_replace(' ', '\s+', $this->opt($this->t('Taxes & charges'))) . "\s*\n+([\s\S]+)/u", $priceTable[2] ?? '', $m)) {
                    $rows = array_filter(explode("\n", $m[1]));
                    $taxes = 0.0;

                    foreach ($rows as $row) {
                        if (preg_match("/^\s*(?<total>\d[\d\.\, \'\’]*?) *(?<currency>{$currency})\s*$/", $row, $m)) {
                            $taxes += PriceHelper::parse($m['total'], $currency);
                        } else {
                            $taxes = 0.0;

                            break;
                        }
                    }

                    if (!empty($taxes)) {
                        $f->price()
                            ->tax($taxes);
                    }
                }

                $fees = [];

                if (preg_match_all("/^ *(?<name>{$this->opt($this->t('Additions Fee'))}) {5,}(?<total>\d[\d\.\, \'\’]*?) *{$currency} *$/um", $priceText, $m)) {
                    foreach ($m[0] as $i => $v) {
                        $fees[$m['name'][$i]] = ($fees[$m['name'][$i]] ?? 0.0) + PriceHelper::parse($m['total'][$i], $currency);
                    }

                    foreach ($fees as $name => $value) {
                        $f->price()
                            ->fee($name, $value);
                    }
                }

                // block Additional Products
                if (preg_match("/\ *(?<name>{$this->opt($this->t('Additional Products:'))})\s*\n(?<text>[\s\S]+?)\n *{$this->opt($this->t('Sum'))} {5,}/um", $priceText, $m)) {
                    $name = $m['name'];
                    $discount = 0.0;

                    if (preg_match_all("/^ *(?<name>(?:\S ?)+) {5,}(?<discount>[\-\−])?(?<total>\d[\d\.\, \'\’]*?) *{$currency} *$/mu", $m['text'], $mat)) {
                        foreach ($mat[0] as $i => $v) {
                            if (!empty($mat['discount'][$i])) {
                                $discount = ($discount ?? 0.0) + PriceHelper::parse($mat['total'][$i], $currency);
                            } else {
                                $f->price()
                                    ->fee($name . ' ' . $mat['name'][$i], PriceHelper::parse($mat['total'][$i], $currency));
                            }
                        }
                    }

                    if (!empty($discount)) {
                        $f->price()
                            ->discount($discount);
                    }
                }
            }
        }

        if (!$f->getPrice()) {
            // парсинг цены из тела письма, если пдф нет
            $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][1]");

            if (!preg_match("/\d/", $price)) {
                $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][2]");
            }

            if (preg_match("/^\s*(?<total>\d[\d\.\, \’\']*?)\s*(?<currency>[A-Z]{3})\s*$/", $price, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            } else {
                $f->price()
                    ->total(null);
            }
        }

        // Parse Pdf Segments
        $pdfTrips = [];

        if (!empty($pdfText) && preg_match("/\n *{$this->opt($this->t('Payment Overview'))}\n([\s\S]+?)\n *{$this->opt($this->t('Description'))} {3,}/u", $pdfText, $m)) {
            $tripText = $m[1];

            $tripText = preg_replace("/\n {20,}{$taxHeaderBeforeDescription}\s*$/u", '', $tripText);
            // удаление части заголовка Taxes & charges, если он указывается в две строки, и одна из них раньше Description
            $tripText = preg_replace("/\n {20,}{$taxHeaderBeforeDescription}\s*$/u", '', $tripText);
            // $this->logger->debug('$tripText = '."\n".print_r( $tripText,true));

            $pos = $this->rowColumnPositions($this->inOneRow($tripText));

            $table = $this->createTable($tripText, $pos, false);

            foreach ($table as $i => &$column) {
                if (empty($column)) {
                    continue;
                }

                if (mb_stripos($column, '•') === false && isset($table[$i + 1])
                    && !preg_match("/(?:^ *| {3}|\n *){$this->opt($this->t('Trip'))} (\d+)(?: *\n| {3})/u", $table[$i + 1], $m)
                    && mb_stripos($table[$i + 1], '•') !== false
                ) {
                    $column = $this->unionColumns($column, $table[$i + 1], '      ');
                    $table[$i + 1] = '';
                }

                $trips = $this->split("/\n *({$this->opt($this->t('Trip'))} \d+ *(?:\n| {3,}))/u", "\n\n" . $column);

                foreach ($trips as $tp) {
                    if (preg_match_all("/(?:^ *| {3}|\n *){$this->opt($this->t('Trip'))} (\d+)(?: *\n| {3})/u", $tp, $m)
                        && count($m[0]) === 1
                        && !isset($pdfTrips[$m[1][0]])
                    ) {
                        $pdfTrips[$m[1][0]] = $tp;
                    } else {
                        $pdfTrips['error'][] = $tp;
                    }
                }
            }

            if (isset($pdfTrips['error'])) {
                $pdfTrips = [];
                $sym = '→';
                $names = array_unique(array_filter(preg_split("/\s*{$sym}\s*/", implode($sym,
                    $this->http->FindNodes("//text()[contains(normalize-space(),'→')]")))));

                if (empty($names)) {
                    $names = array_unique(array_filter(
                        $this->http->FindNodes("//tr[count(*) = 3][*[2][not(normalize-space())]][*[1][contains(., '(')]][*[3][contains(., '(')]]/*", null, "/^\s*(.+?)\s*\([A-Z]{3}\)\s*$/")));
                }

                $namePt = '/( [^\| ]+)(\||\)$)/u';
                $nameRe = '(?:$1)?$2';

                if (!empty($names)
                    && (preg_match("/^{$this->opt($this->t('Trip'))} \d+ +.+\n+( *{$this->opt($names)} - {$this->opt($names)}(?:.+?\S)? ){$this->opt($names)} - {$this->opt($names)}/u", $tripText, $m)
                        || preg_match("/^{$this->opt($this->t('Trip'))} \d+ +.+\n+( *{$this->opt($names)} - " . preg_replace($namePt, $nameRe, $this->opt($names)) . "(?:.+?\S)? ){$this->opt($names)} - " . preg_replace($namePt, $nameRe, $this->opt($names)) . "/u", $tripText, $m)
                    )
                ) {
                    // разделение на два столбца, когда информация о рейсах объединяется в одну строку
                    // Trip 1                                                               Trip 2
                    // Johannesburg - Paris Charles De Gaulle , 2024/04 Paris Charles De Gaulle - Johannesburg , 2024/05
                    // /29                                                                  /09
                    // Economy             Swiss • LX283                                    Economy              Swiss • LX645

                    // добавляются 20 пробелов в объединенную строку для разделения
                    $tripText = str_replace($m[1], $m[1] . str_pad('', 20, ' '), $tripText);
                    // добавляются +3 пробела к каждым 20 в остальные строки, для таких случаев
                    // Trip 1                                           Trip 2
                    // Johannesburg - Paris Charles De Gaulle , 2024/04 Paris Charles De Gaulle - Johannesburg , 2024/05
                    // /29                                              /09
                    // Economy        Swiss • LX283                     Economy           Swiss • LX645
                    $tripText = str_replace(str_pad('', 20, ' '), str_pad('', 23, ' '), $tripText);

                    $table = $this->createTable($tripText, $this->rowColumnPositions($this->inOneRow($tripText)), false);

                    if (count($table) == 3 && preg_match("/^\s*{$this->opt($this->t('Trip'))} (\d+)\n/u", $table[1], $m)
                        && !preg_match("/\b{$this->opt($this->t('Trip'))} (\d+)\b/u", $table[2], $m)
                    ) {
                        // если из-за добавления пробелов, 2 колонка разбивается на две
                        $table[1] = $this->unionColumns($table[1], $table[2]);
                        unset($table[2]);
                    }

                    foreach ($table as $column) {
                        $column = preg_replace('/(^ *| *$)/m', '', $column);
                        $trips = $this->split("/\n *({$this->opt($this->t('Trip'))} \d+\n)/u", "\n\n" . $column);

                        foreach ($trips as $tp) {
                            if (preg_match_all("/(?:^ *| {3}|\n *){$this->opt($this->t('Trip'))} (\d+)(?: *\n| {3})/u", $tp, $m)
                                && count($m[0]) === 1
                                && !isset($pdfTrips[$m[1][0]])
                            ) {
                                $pdfTrips[$m[1][0]] = $tp;
                            } else {
                                $pdfTrips['error'][] = $tp;
                            }
                        }
                    }
                }
            }
        }
        // $this->logger->debug('$pdfTrips = '.print_r( $pdfTrips,true));

        // parse seatsText form pdf
        $seatsText = '';

        if ($pdfText && $this->containsText($pdfText, $this->t('Seat reservations'))) {
            if (preg_match_all("/\n *{$this->opt($this->t('Seat reservations'))}(?: {5,}.*)?\n"
                . "((?: *.+ - .+ • {0,2}\S.*\n)+)/u", $pdfText, $m)
            ) {
                $seatsText = implode("\n", $m[1]);
                $seatsText = preg_replace("/\n\s*\n/m", "\n", $seatsText);
                $seatsText = preg_replace("/^\s*(\S.+?• {0,3}(\d{1,3}[A-Z]))(?: {5,}.+)?\s*$/um", '$1', $seatsText);
            }
        }
        // $this->logger->debug('$seatsText = ' . print_r($seatsText, true));

        $xpath = "//text()[{$this->eq($this->t('Your Trip Summary'))}]/ancestor::table[1]/following::table[1]//tr[not(.//tr)]";
        $textParts = [];

        foreach ($this->http->XPath->query($xpath) as $xroot) {
            $textParts[] = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $xroot));
        }
        $flightsText = implode("\n\n\n", $textParts);

        $flightsText = preg_replace("/\n\s*\d+\s*{$this->opt($this->t('passenger(s):'))}[\s\S]*/", '', $flightsText);

        if (preg_match("/^\s*(?:\+\d\n\s*)?([[:alpha:]]+(?: [[:alpha:]\p{Thai}]+)? •) .*(?:\b|\D)\d{4}(?:\b|\D).*\n/u", $flightsText, $m)) {
            $segments = $this->split("/\n\n\n({$m[1]})/u", "\n\n\n\n" . $flightsText);
        } else {
            $segments = $this->split("/\n\n\n((?:.+\n?){1,3}?\s+→\s+(?:.+\n?){1,3}?\n(?:.+\n?){1,3}?\s+[→]\s+[^\n]+)/u",
                "\n\n\n\n" . $flightsText);
        }
        // contains departureName → arrivalName
        $re = "/^\s*(?<depPart1>.+)\s+[→]\s+(?<arrPart1>.+)\n(?<depPart2>.+)\s+[→]\s+(?<arrPart2>.+)"
            . "\n(?<depTime>\d+\:\d+)\s*(?<depDate>.+)\s+\-\s+(?<arrTime>\d+\:\d+)\s+(?<arrDate>.+)\n"
            . "(?<airline>.+)\s+[•]\s+(?:[[:alpha:]]+(?: [[:alpha:]]+)?|(?<stops>\d+) {$this->opt($this->t('Stop(s)'))})\s*/us";
        // $this->logger->debug('$re = '.print_r( $re,true));

        // starts with Depart • {date}
        $re2 = "/^\s*(?:\+\d\n\s*)?[[:alpha:]\p{Thai}]+(?: [[:alpha:]\p{Thai}]+)? •\s*(?<date>.+?)\n\s*(?<depTime>\d+\:\d+(?: ?[AP]M)?)\s+(?<arrTime>\d+\:\d+(?: ?[AP]M)?)(?<overnight>\s+[\-+]\d)?\n\s*"
            . "(?<duration>[\w \.\p{Thai}]+?)( *[,,] *(?:乗り換え\s*)?(?<stops>\d+) {$this->opt($this->t('Stop(s)'))})?\n(?:.*\n)?\s*"
            . "(?<depPart1>.+?) ?\((?<depCode>[A-Z]{3})\)\n\s*(?<arrPart1>.+?) ?\((?<arrCode>[A-Z]{3})\)"
            . "(?<namesPart2>\n.*)/us";
        // $this->logger->debug('$re2 = '.print_r( $re2,true));

        $reCabinFlight = "/^ *(?:(?<cabin>[\w\p{Thai}](?: ?[\w\-\p{Thai}])+?)[ ]{4,})?(?:\S ?)+\s*[•]\s*(?<an>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fn>\d{1,5})\s*$/u";

        if (isset($pdfTrips['error']) || count($pdfTrips) !== count($segments)) {
            $pdfTrips = [];
        }

        $flightsSegments = [];

        foreach ($segments as $i => $sText) {
            // $this->logger->debug('$sText = '.print_r( $sText,true));
            $i = $i + 1;
            $s = $f->addSegment();

            if (preg_match($re, $sText, $m)
                || preg_match($re2, $sText, $m)
            ) {
                if (!empty($m['stops']) && $m['stops'] > 1) {
                    $email->removeItinerary($f);
                    $email->setIsJunk(true, 'more than 2 stops in segment');
                    $this->logger->info('more than 2 stops in segment');

                    return true;
                } elseif (!empty($m['stops']) && $m['stops'] == 1 && empty($pdfText)) {
                    $email->removeItinerary($f);
                    $email->setIsJunk(true, 'more than 1 stops in segment without flight number');
                    $this->logger->info('more than 1 stops in segment without flight number');

                    return true;
                }

                if (!empty($m['namesPart2'])) {
                    $namesP = explode("\n", trim($m['namesPart2']));

                    if (count($namesP) === 2) {
                        $m['depPart2'] = $namesP[0];
                        $m['arrPart2'] = $namesP[1];
                    }
                }
                $depCode = $m['depCode'] ?? null;
                $arrCode = $m['arrCode'] ?? null;

                $depNameP1 = $m['depPart1'];
                $depName = $m['depPart1'] . (!empty($m['depPart2']) ? ', ' . $m['depPart2'] : '');
                $arrNameP1 = $m['arrPart1'];
                $arrName = $m['arrPart1'] . (!empty($m['arrPart2']) ? ', ' . $m['arrPart2'] : '');

                $depDate = $this->normalizeDate(($m['depDate'] ?? $m['date']) . ', ' . $m['depTime']);
                $arrDate = $this->normalizeDate(($m['arrDate'] ?? $m['date']) . ', ' . $m['arrTime']);

                if (!empty($arrDate) && !empty($m['overnight'])) {
                    $arrDate = strtotime($m['overnight'] . ' day', $arrDate);
                }

                $pdfTripRows = [];

                if (isset($pdfTrips[$i])) {
                    $pdfTrips[$i] = preg_replace("/\-\n/", "-", $pdfTrips[$i]);
                }

                if (isset($pdfTrips[$i])
                    && preg_match("/\n *" . str_replace(' ', '\s+', $this->opt($depNameP1)) . " *- *" . str_replace(' ', '\s+', $this->opt($arrNameP1)) . "\s*,(?:[\d\/\s]+|[\d\-\s]+|[\d\.\s]+(?:г\.)?)\s*\n([\s\S]+?)\s*$/u", $pdfTrips[$i], $pm)
                ) {
                    $pdfTripRows = array_values(array_filter(explode("\n", $pm[1])));
                }

                if (empty($m['stops'])) {
                    if (!empty($depCode)) {
                        $s->departure()
                            ->code($depCode);
                    } else {
                        $s->departure()
                            ->noCode();
                    }
                    $s->departure()
                        ->name($depName)
                        ->date($depDate);

                    if (!empty($arrCode)) {
                        $s->arrival()
                            ->code($arrCode);
                    } else {
                        $s->arrival()
                            ->noCode();
                    }
                    $s->arrival()
                        ->name($arrName)
                        ->date($arrDate);

                    if (count($pdfTripRows) == 1 && preg_match($reCabinFlight, $pdfTripRows[0], $pm)) {
                        $s->airline()
                            ->name($pm['an'])
                            ->number($pm['fn']);

                        if (!empty($pm['cabin'])) {
                            $s->extra()
                                ->cabin($pm['cabin']);
                        }
                    } else {
                        if (empty($m['airline'])) {
                            $s->airline()
                                ->noName();
                        } else {
                            $s->airline()
                                ->name($m['airline']);
                        }
                        $s->airline()
                            ->noNumber();
                    }

                    if (!empty($m['duration'])) {
                        $s->extra()
                            ->duration($m['duration']);
                    }

                    if (!empty($seatsText)) {
                        if (
                            preg_match_all("/^{$this->cityNamesRe($depNameP1)} *- *{$this->cityNamesRe($arrNameP1)} * • (\d{1,3}[A-Z])$/m", $seatsText, $sm)
                            || preg_match_all("/^{$this->cityNamesRe($depNameP1, true)} *- *{$this->cityNamesRe($arrNameP1, true)} * • (\d{1,3}[A-Z])$/m", $seatsText, $sm)
                        ) {
                            // Toronto Pearson Intl - Edinburgh , 29/08/2024
                            // Seat Reservation
                            //      Toronto - Edinburgh • 17A
                            $s->extra()
                                ->seats($sm[1]);

                            foreach ($sm[0] as $v) {
                                $seatsText = str_replace($v, '', $seatsText);
                            }
                        }
                    }
                } else {
                    if (!empty($depCode)) {
                        $s->departure()
                            ->code($depCode);
                    } else {
                        $s->departure()
                            ->noCode();
                    }

                    $s->departure()
                        ->name($depName)
                        ->date($depDate);
                    $s->arrival()
                        ->noCode()
                        ->noDate();

                    $s1 = $f->addSegment();
                    $s1->departure()
                        ->noCode()
                        ->noDate();

                    if (!empty($arrCode)) {
                        $s1->arrival()
                            ->code($arrCode);
                    } else {
                        $s1->arrival()
                            ->noCode();
                    }
                    $s1->arrival()
                        // ->noCode()
                        ->name($arrName)
                        ->date($arrDate);

                    if (count($pdfTripRows) == 2
                        && preg_match($reCabinFlight, $pdfTripRows[0], $pm1)
                        && preg_match($reCabinFlight, $pdfTripRows[1], $pm2)
                    ) {
                        $s->airline()
                            ->name($pm1['an'])
                            ->number($pm1['fn']);

                        if (!empty($pm1['cabin'])) {
                            $s->extra()
                                ->cabin($pm1['cabin']);
                        }

                        $s1->airline()
                            ->name($pm2['an'])
                            ->number($pm2['fn']);

                        if (!empty($pm2['cabin'])) {
                            $s1->extra()
                                ->cabin($pm2['cabin']);
                        }

                        $flightsSegments[] = [
                            'al'  => $pm1['an'],
                            'fn'  => $pm1['fn'],
                            'dep' => $depNameP1,
                            'arr' => null,
                        ];
                        $flightsSegments[] = [
                            'al'  => $pm2['an'],
                            'fn'  => $pm2['fn'],
                            'dep' => null,
                            'arr' => $arrNameP1,
                        ];
                    } else {
                        if (preg_match("/^\s*(.+?) \+ \d+/", $m['airline'], $fm)) {
                            // WestJet + 1
                            $s->airline()
                                ->name($fm[1])
                                ->noNumber();
                            $s1->airline()
                                ->noName()
                                ->noNumber();
                        } else {
                            if (!empty($m['airline'])) {
                                $s->airline()
                                    ->name($m['airline']);
                            } else {
                                $s->airline()
                                    ->noName();
                            }
                            $s->airline()
                                ->noNumber();

                            if (!empty($m['airline'])) {
                                $s1->airline()
                                    ->name($m['airline']);
                            } else {
                                $s1->airline()
                                    ->noName();
                            }
                            $s1->airline()
                                ->noNumber();
                        }
                    }
                }
            }
        }

        // Дополнение названий городов и номеров сидений, для сегментов где указан только 1 аэропорт
        if (!empty(trim($seatsText)) && !empty($flightsSegments)) {
            $seatsRow = array_filter(explode("\n", $seatsText));
            $seatsByFlight = [];

            foreach ($seatsRow as $row) {
                if (preg_match("/^\s*(.+?\s*-\s*\S.+?)\s*•\s*(.+)$/u", $row, $m)) {
                    $seatsByFlight[$m[1]][] = $m[2];
                }
            }
            // $this->logger->debug('$seatsByFlight = '.print_r( $seatsByFlight,true));
            // $this->logger->debug('$flightsSegments = '.print_r( $flightsSegments,true));

            if (count($seatsByFlight) === count($flightsSegments)) {
                $i = 0;

                foreach ($seatsByFlight as $name => $seatsArray) {
                    $fs = $flightsSegments[$i];

                    if (
                        preg_match("/^({$this->cityNamesRe($fs['dep'])}) *- *({$this->cityNamesRe($fs['arr'])}) *$/u", $name, $m)
                        || preg_match("/^({$this->cityNamesRe($fs['dep'], true)}) *- *({$this->cityNamesRe($fs['arr'], true)}) *$/u", $name, $m)
                    ) {
                        $flightsSegments[$i]['seats'][] = $seatsArray;

                        if (empty($fs['dep'])) {
                            $flightsSegments[$i]['dep'] = $m[1];
                        }

                        if (empty($fs['arr'])) {
                            $flightsSegments[$i]['arr'] = $m[2];
                        }
                    } else {
                        $flightsSegments = [];

                        break;
                    }
                    $i++;
                }
            } else {
                foreach ($seatsByFlight as $name => $seatsArray) {
                    foreach ($flightsSegments as $i => $fs) {
                        if (
                            preg_match("/^({$this->cityNamesRe($fs['dep'])}) *- *({$this->cityNamesRe($fs['arr'])}) *$/u", $name, $m)
                            || preg_match("/^({$this->cityNamesRe($fs['dep'], true)}) *- *({$this->cityNamesRe($fs['arr'], true)}) *$/u", $name, $m)
                        ) {
                            $flightsSegments[$i]['seats'][] = $seatsArray;

                            if (empty($fs['dep'])) {
                                $flightsSegments[$i]['dep'] = $m[1];
                            }

                            if (empty($fs['arr'])) {
                                $flightsSegments[$i]['arr'] = $m[2];
                            }
                        }
                    }
                }
            }

            // $this->logger->debug('$flightsSegments = '.print_r( $flightsSegments,true));
            foreach ($flightsSegments as $i => $fs) {
                if (empty($fs['seats']) || count($fs['seats']) !== 1) {
                    continue;
                }

                foreach ($f->getSegments() as $s) {
                    if ($s->getAirlineName() === $fs['al'] && $s->getFlightNumber() === $fs['fn']
                        && ((!empty($fs['dep']) && strpos($s->getDepName(), $fs['dep']) === 0 || (!empty($fs['arr']) && strpos($s->getArrName(), $fs['arr']) === 0)))
                    ) {
                        $fs['seats'][0] = array_filter($fs['seats'][0], function ($s) {return preg_match("/^\d{1,3}[A-Z]$/", $s); });

                        if (!empty($fs['seats'][0])) {
                            $s->extra()
                                ->seats($fs['seats'][0]);
                        }

                        if (empty($s->getDepName()) && !empty($fs['dep'])) {
                            $s->departure()
                                ->name($fs['dep']);
                        }

                        if (empty($s->getArrName()) && !empty($fs['arr'])) {
                            $s->arrival()
                                ->name($fs['arr']);
                        }
                    }
                }
            }
        }
    }

    public function cityAlternativeName($name): array
    {
        $result = [];
        $cityNameReplaces = [
            'Milan Malpensa'      => ['Milano'],
            'Sao Paulo-Guarulhos' => ['Sao Paulo'],
            'Turin'               => ['Torino'],
            'Budapest'            => ['Будапеща'],
            'Manila'              => ['Манила'],
            'Nuremberg Airport'   => ['Нюрнберг'],
            'Geneva'              => ['Genève'],
            'Prague'              => ['Praha'],
            'Ho Chi Minh City'    => ['Ho Či Minovo Město'],
        ];

        if (isset($cityNameReplaces[$name])) {
            $result = $cityNameReplaces[$name];
        }
        $result[] = $name;

        return $result;
    }

    public function cityNamesRe($name, $replaceDash = false)
    {
        $result = [];

        if (!empty($name)) {
            $names = $this->cityAlternativeName($name);
            $symbols = ' ';

            if (!empty($replaceDash)) {
                $symbols .= '-';
            }
            $result = array_map(function ($v) use ($symbols, $replaceDash) {
                $v = preg_quote($v, '/');

                if ($replaceDash) {
                    $v = str_replace('\-', '-', $v);
                }
                $v = preg_replace(
                    "/([" . preg_quote($symbols, '/') . "]+)/",
                    '(?:$1',
                    $v,
                    -1, $count);
                $v .= str_repeat(")?", $count);

                return $v;
            }, $names);
        } else {
            $result = ['\S.+'];
        }

        return '(?:' . implode('|', $result) . ')';
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date IN = '.print_r( $date,true));

        $in = [
            // Sunday 07 April, 2024, 12:25
            // fredag den 05. juli 2024, 17:00
            "/^\s*[[:alpha:]\p{Thai}\-]+(?:\s+den)?\s+(\d{1,2})[.]?\s+([[:alpha:]\p{Thai}]+)\s*[,\s]\s*(\d{4})\s*,\s*(\d{1,2}:\d{2})\s*$/iu",
            // 목요일 15 8월, 2024, 18:00
            // 月曜日 19 8 月, 2024, 15:40
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\s+(\d{1,2})\s*(?:월|月)\s*[,\s]\s*(\d{4})\s*,\s*(\d{1,2}:\d{2})\s*$/iu",
            // 2024년12월14일, 20:55
            // 2025年2月03日, 06:35
            "/^\s*(\d{4})\s*[년年]\s*(\d{1,2})\s*[월月]\s*(\d{1,2})\s*[일日]\s*[,\s]\s*(\d{1,2}:\d{2})\s*$/iu",
            // mercredi, janvier 22, 2025, 16:10
            "/^\s*[[:alpha:]\-]+\s*[,\s]\s*([[:alpha:]\p{Thai}]+)\s+(\d{1,2})\s*[,\s]\s*(\d{4})\s*,\s*(\d{1,2}:\d{2})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$3-$2-$1, $4",
            "$1-$2-$3, $4",
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date RE = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(\d{4})#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }

            if ($this->lang === 'th' && $m[2] > 2500) {
                $date = str_replace($m[2], $m[2] - 543, $date);
            }
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = [], $trim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                if ($trim) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function unionColumns($col1, $col2, $unionText = ' ')
    {
        $col1Rows = explode("\n", $col1);
        $col2Rows = explode("\n", $col2);
        $newCol = '';

        for ($c = 0; $c < max(count($col1Rows), count($col2Rows)); $c++) {
            $newCol .= ($col1Rows[$c] ?? '') . $unionText . ($col2Rows[$c] ?? '') . "\n";
        }

        return $newCol;
    }
}
