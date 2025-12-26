<?php

namespace AwardWallet\Engine\tcase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\aeroflot\Email\ETicketPdf;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers velocity/ETicket2, kulula/FlightConfirm (in favor of tcase/It5045494)

class It5045494 extends \TAccountChecker
{
    public $mailFiles = "tcase/it-1.eml, tcase/it-10.eml, tcase/it-10195753.eml, tcase/it-105916175.eml, tcase/it-10667653.eml, tcase/it-10722874.eml, tcase/it-10955814.eml, tcase/it-11.eml, tcase/it-11141309.eml, tcase/it-11146757.eml, tcase/it-12086236.eml, tcase/it-12141427.eml, tcase/it-12631869.eml, tcase/it-12631876.eml, tcase/it-13603981.eml, tcase/it-14.eml, tcase/it-14474310.eml, tcase/it-14614621.eml, tcase/it-1556639.eml, tcase/it-1556844.eml, tcase/it-1556849.eml, tcase/it-1591360.eml, tcase/it-1591362.eml, tcase/it-1627155.eml, tcase/it-1645204.eml, tcase/it-1652089.eml, tcase/it-1661474.eml, tcase/it-1670871.eml, tcase/it-1679587.eml, tcase/it-1681233.eml, tcase/it-1692936.eml, tcase/it-1802120.eml, tcase/it-1810155.eml, tcase/it-1823329.eml, tcase/it-1826922.eml, tcase/it-1843767.eml, tcase/it-1863083.eml, tcase/it-1875570.eml, tcase/it-1901454.eml, tcase/it-1913564.eml, tcase/it-2.eml, tcase/it-2001117.eml, tcase/it-2003034.eml, tcase/it-2004355.eml, tcase/it-2068959.eml, tcase/it-2081664.eml, tcase/it-2107324.eml, tcase/it-2131010.eml, tcase/it-2144649.eml, tcase/it-2347128.eml, tcase/it-2362252.eml, tcase/it-2362363.eml, tcase/it-2362519.eml, tcase/it-2390664.eml, tcase/it-2390677.eml, tcase/it-2390678.eml, tcase/it-2390679.eml, tcase/it-2390682.eml, tcase/it-2417919.eml, tcase/it-2429481.eml, tcase/it-2466835.eml, tcase/it-2480691.eml, tcase/it-2489472.eml, tcase/it-2489474.eml, tcase/it-2491318.eml, tcase/it-2501137.eml, tcase/it-2501179.eml, tcase/it-2501360.eml, tcase/it-2514150.eml, tcase/it-2540964.eml, tcase/it-2636804.eml, tcase/it-2642244.eml, tcase/it-2664169.eml, tcase/it-2732945.eml, tcase/it-2739330.eml, tcase/it-2753571.eml, tcase/it-2848067.eml, tcase/it-2955201-aerolineas-es.eml, tcase/it-2997268.eml, tcase/it-3.eml, tcase/it-3023274.eml, tcase/it-3033260.eml, tcase/it-31132181.eml, tcase/it-3231347.eml, tcase/it-32690996.eml, tcase/it-3356552.eml, tcase/it-34291693.eml, tcase/it-3479732.eml, tcase/it-3482747.eml, tcase/it-3489463.eml, tcase/it-3664226.eml, tcase/it-3903469.eml, tcase/it-39120394.eml, tcase/it-3951512.eml, tcase/it-4020113.eml, tcase/it-4044177.eml, tcase/it-40480018.eml, tcase/it-4262198.eml, tcase/it-4344772.eml, tcase/it-4408817.eml, tcase/it-4423783.eml, tcase/it-4488158.eml, tcase/it-4533545.eml, tcase/it-4534835.eml, tcase/it-462112675.eml, tcase/it-4627976.eml, tcase/it-4677413.eml, tcase/it-468644293.eml, tcase/it-4721699.eml, tcase/it-4951310.eml, tcase/it-5010322.eml, tcase/it-5045192.eml, tcase/it-5070057.eml, tcase/it-5089606.eml, tcase/it-5366635.eml, tcase/it-5368141.eml, tcase/it-57967064.eml, tcase/it-5889898.eml, tcase/it-6084725.eml, tcase/it-6084729.eml, tcase/it-6123982.eml, tcase/it-6213344.eml, tcase/it-6232642.eml, tcase/it-6232763.eml, tcase/it-628488929.eml, tcase/it-628746939.eml, tcase/it-6334066.eml, tcase/it-6401235.eml, tcase/it-6457888.eml, tcase/it-6660947.eml, tcase/it-6660951.eml, tcase/it-7104124.eml, tcase/it-8.eml, tcase/it-8885031.eml, tcase/it-9050073.eml, tcase/it-9779119.eml, tcase/it-9781308.eml, tcase/it-702885371-airserbia-bs.eml";

    public static $detectProviders = [
        'aeroflot' => [
            'iata'           => 'SU',
            'isTravelAgency' => false,
            'from'           => ['@aeroflot.ru'],
            'uniqueSubject'  => [],
            'body'           => [
                'Благодарим Вас за то, что Вы выбрали Аэрофлот',
                'Thanks for choosing Aeroflot',
            ],
            'href'   => ['aeroflot.ru'],
            'agency' => [],
        ],
        'aerolineas' => [
            'iata'           => 'AR',
            'isTravelAgency' => false,
            'from'           => ['aerolineas.com.ar'],
            'uniqueSubject'  => ['Aerolineas Argentinas.Travel Reservation to', 'Aerolineas Argentinas.Reserva de viaje'],
            'body'           => [
                'purchasing your ticket with Aerolineas Argentinas',
                'Gracias por reservar tu pasaje en Aerolíneas Argentinas',
                'Grazie per aver acquistato il tuo biglietto su Aerolíneas Argentinas',
                'Gracias por comprar tu pasaje en Aerolíneas Argentinas',
            ],
            'href' => [
                '.aerolineas.',
            ],
            'agency' => [],
        ],
        'aeromexico' => [
            'iata'           => 'AM',
            'isTravelAgency' => false,
            'from'           => ['aeromexico.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thank you for choosing Aeromexico',
                '¡Gracias por elegir volar con Aeroméxico',
            ],
            'href' => [
                '.aeromexico.com',
            ],
            'agency' => [],
        ],
        'airmalta' => [
            'iata'           => 'KM',
            'isTravelAgency' => false,
            'from'           => ['airmalta.com'],
            'body'           => [
                'Thanks for choosing Air Malta',
            ],
            'href' => [
                '.airmalta.',
            ],
            'agency' => [],
        ],
        'airserbia' => [
            'iata'           => 'JU',
            'isTravelAgency' => false,
            'from'           => ['noreply@airserbia.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'From Serbia: 0800 111 528',
            ],
            'href' => [
                'www.airserbia.com',
            ],
            'agency' => [],
        ],
        'alitalia' => [
            'iata'           => 'AZ',
            'isTravelAgency' => false,
            'from'           => ['@alitalia.'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [
                'alitalia.com',
            ],
            'agency' => [],
        ],
        'amextravel' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['americanexpress'],
            'uniqueSubject'  => ['American Express'],
            'body'           => [
                'American Express Global Business Travel',
            ],
            'href' => [
                '.amexgbt.com',
            ],
            'agency' => ['American Express', 'AMERICAN EXPRESS TRAVEL'],
        ],
        'bahamasair' => [
            'iata'           => 'UP',
            'isTravelAgency' => false,
            'from'           => ['bahamasair.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thanks for choosing Bahamasair',
            ],
            'href' => [
                '.bahamasair.com',
            ],
            'agency' => [],
        ],
        'belavia' => [
            'iata'           => 'B2',
            'isTravelAgency' => false,
            'from'           => ['belavia.by'],
            'uniqueSubject'  => [
                'Belavia Belarusian Airlines - Reservation Confirmation:',
            ],
            'body' => [
                'Thank you for booking with Belavia',
            ],
            'href' => [
                'belavia.by',
            ],
            'agency' => [],
        ],
        'bcd' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['bcdtravelmexico.com.mx', 'BCD TRAVEL'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [],
            'agency'         => ['BCD TRAVEL'],
        ],
        'cayman' => [
            'iata'           => 'KX',
            'isTravelAgency' => false,
            'from'           => ['DONOTREPLY.CAYMANAIRWAYS@SABRE.COM'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thank you for choosing Cayman Airways',
            ],
            'href' => [
                '.caymanairways.com',
            ],
            'agency' => [],
        ],
        'ctmanagement' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['.travelctm.com'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => ['.travelctm.'],
            'agency'         => [
                'Corporate Travel Management',
            ],
        ],
        'ctraveller' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['@corporatetraveller'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => ['corporatetraveller.'],
            'agency'         => [
                'Corporate Traveller',
                'Corporate Traveler',
            ],
        ],
        'directravel' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['SERVICES DIRECT TRAVEL'], //SERVICES DIRECT TRAVEL <confirmation@tripcase.com>
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [],
            'agency'         => [
                'DIRECT TRAVEL',
            ],
        ],
        'ethiopian' => [
            'iata'           => 'ET',
            'isTravelAgency' => false,
            'from'           => ['ethiopianairlines.com'],
            'uniqueSubject'  => [
                'Your Etihad Airways reference :',
            ],
            'body' => [
                'brought to you by Ethiopian Airlines',
                'Thank you for choosing Ethiopian',
                '@ethiopianairlines.com',
            ],
            'href' => [
                'ethiopianairlines.com',
            ],
            'agency' => [],
        ],
        'etihad' => [
            'iata'           => 'EY',
            'isTravelAgency' => false,
            'from'           => ['@etihad.'],
            'uniqueSubject'  => [],
            'body'           => [
                'The Etihad Airways',
                'Thank you for choosing to fly with Etihad Airways',
            ],
            'href' => [
                '.etihad.',
                'etihadguest.com',
            ],
            'agency' => [],
        ],
        'fcmtravel' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['farmacity@furlong-fox.com.ar'],
            //            'uniqueSubject'  => [],
            //            'body'           => [],
            //            'href'           => [],
            'agency'         => ['Furlong Fox - FCM'],
        ],
        'stuniverse' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['@studentuniverse.co.uk'],
            'uniqueSubject'  => [],
            'body'           => ['please contact: HELP@STUDENTUNIVERSE.CO.UK'],
            'href'           => [],
            'agency'         => [],
        ],
        'flightcentre' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['@flightcentre.com.au'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => ['fctgl.com'],
            'agency'         => ['Flight Centre', 'FLIGHT CENTRE'],
        ],
        'flyerbonus' => [
            'iata'           => 'PG',
            'isTravelAgency' => false,
            'from'           => ['bangkokair.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thanks for booking with Bangkok Airways',
            ],
            'href' => [
                '.bangkokair.com',
            ],
            'agency' => [],
        ],
        'frontierairlines' => [
            'iata'           => 'F9',
            'isTravelAgency' => false,
            'from'           => ['flyfrontier.com'],
            'uniqueSubject'  => [],
            'body'           => [
            ],
            'href' => [
                '.flyfrontier.com',
            ],
            'agency' => [],
        ],
        'gulfair' => [
            'iata'           => 'GF',
            'isTravelAgency' => false,
            'from'           => [],
            'uniqueSubject'  => [],
            'body'           => [
                'Gulf Air',
            ],
            'href' => [
                'gulfair.com',
            ],
            'agency' => [],
        ],
        'golair' => [
            'iata'           => 'G3',
            'isTravelAgency' => false,
            'from'           => ['noreply@voegol.com.br'],
            'uniqueSubject'  => [
                ' Reserva Gol ',
            ],
            'body'           => [
                'Obrigada por escolher a GOL.',
            ],
            'href' => [
                '.voegol.com.br',
            ],
            'agency' => [],
        ],
        'hawaiian' => [
            'iata'           => 'HA',
            'isTravelAgency' => false,
            'from'           => ['hawaiianairlines.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'For inquiries regarding your reservation, please contact Hawaiian Airlines at',
                'Mahalo for choosing Hawaiian Airlines',
            ],
            'href' => [
                'hawaiianairlines.com',
            ],
            'agency' => [],
        ],
        'klm' => [
            'iata'           => 'KL',
            'isTravelAgency' => false,
            'from'           => ['@airfrance-klm.com'],
            'uniqueSubject'  => [
                'Flying Blue',
            ],
            'body'   => ['www.flyingblue.com'],
            'href'   => ['www.flyingblue.com'],
            'agency' => [],
        ],
        'kulula' => [
            'iata'           => 'MN',
            'isTravelAgency' => false,
            'from'           => [],
            'uniqueSubject'  => [],
            'body'           => [
                'kulula booking reference',
            ],
            'href'   => [],
            'agency' => [],
        ],
        'lanpass' => [
            'iata'           => 'LA',
            'isTravelAgency' => false,
            'from'           => ['@cc.lan.com', '@latam.com'],
            'uniqueSubject'  => [
                'LATAM Airlines',
            ],
            'body' => [
                'Gracias por elegir LAN',
                'Thanks for choosing LATAM Airlines',
            ],
            'href' => [
                '.latam.com',
                '.lan.com',
            ],
            'agency' => [],
        ],
        'lastminute' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['lastminute.com'],
            'uniqueSubject'  => [],
            'body'           => [
            ],
            'href' => [
                'lastminute.com',
            ],
            'agency' => ['lastminute.com'],
        ],
        'lionair' => [
            // 'iata' => '',
            'isTravelAgency' => false,
            'from'           => ['lionairthai.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thanks for choosing Thai Lion Air',
            ],
            'href' => [
                '.lionairthai.com',
            ],
            'agency' => [],
        ],
        'mabuhay' => [
            'iata'           => 'PR',
            'isTravelAgency' => false,
            'from'           => ['philippineairlines.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thank you for choosing Philippine Airlines',
            ],
            'href' => [
                'philippineairlines.com',
            ],
            'agency' => [],
        ],
        'malindoair' => [
            'iata'           => 'OD',
            'isTravelAgency' => false,
            'from'           => ['malindoair.com'],
            'uniqueSubject'  => [
                'Malindo Air eTicket',
            ],
            'body' => [
                'Thanks for choosing Malindo Air',
            ],
            'href' => [
                'malindoair.com',
            ],
            'agency' => [],
        ],
        'oman' => [
            'iata'           => 'WY',
            'isTravelAgency' => false,
            'from'           => ['omanair.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'choosing to fly with Oman Air',
            ],
            'href' => [
                '.omanair.com',
            ],
            'agency' => [],
        ],
        'pia' => [
            'iata'           => 'PK',
            'isTravelAgency' => false,
            'from'           => [
                'piac.aero',
            ],
            'uniqueSubject' => [],
            'body'          => [
                'Thank you for choosing PIA',
                'Thank you for choosing Pakistan International Airlines',
            ],
            'href'   => [],
            'agency' => [],
        ],
        'powertravel' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['@powertravel.net'],
            'uniqueSubject'  => [],
            'body'           => [
                'POWER TRAVEL',
            ],
            'href'   => ['powertravel.net'],
            'agency' => [
                'POWER TRAVEL',
            ],
        ],
        'silverairways' => [
            // 'iata' => '',
            'isTravelAgency' => false,
            'from'           => ['@silverairways.com', '@flysilver.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'at SilverAirways.com',
            ],
            'href'   => ['.flysilver.com'],
            'agency' => [],
        ],
        'tcase' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            //            'from' => ['@tripcase.com'],
            //            'subj' => [
            //                'Travel Reservation to',
            //                ', we loaded your trip to',
            //            ],
            //            'body' => [
            //                '//a[contains(@href,\'tripcase.com\')]',
            //                '.tripcase.com',
            //            ],
            //            'body' => [],
            //            'href' => ['.tripcase.com'],
        ],
        'travelocitybus' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['tbiztravel.com'],
            'uniqueSubject'  => [],
            'body'           => [
                '@tbiztravel.com',
            ],
            'href'   => [],
            'agency' => [],
        ],
        'trplace' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => [],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [],
            'agency'         => ['TRAVEL PLACE'],
        ],
        'tzell' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => [],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [],
            'agency'         => ['THE TZELL TRAVEL GROUP'],
        ],
        'ufly' => [
            'iata'           => 'SY',
            'isTravelAgency' => false,
            'from'           => ['suncountry.com'],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [
                '.suncountry.com',
            ],
            'agency' => [],
        ],
        'velocity' => [
            'iata'           => 'VA',
            'isTravelAgency' => false,
            'from'           => ['virginaustralia.com'],
            'uniqueSubject'  => [
                'Virgin Australia e-Ticket',
            ],
            'body' => [
            ],
            'href' => [
                '.virginaustralia.',
            ],
            'agency' => [],
        ],
        'vietnam' => [
            'iata'           => 'VN',
            'isTravelAgency' => false,
            'from'           => ['vietnamairlines.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Cám ơn Quý khách đã mua vé của Vietnam Airlines',
                'for purchasing your ticket with Vietnam Airlines',
                'votre billet auprès de Vietnam Airlines',
            ],
            'href' => [
                'vietnamairlines.com',
            ],
            'agency' => ['Vietnam Airlines'],
        ],
        'wagonlit' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => ['contactcwt.'],
            'uniqueSubject'  => [],
            'body'           => [
                'ORACLE TRAVEL',
            ],
            'href'   => [],
            'agency' => ['ORACLE TRAVEL'],
        ],
        'westjet' => [
            'iata'           => 'WS',
            'isTravelAgency' => false,
            'from'           => ['westjet.com'],
            'uniqueSubject'  => [],
            'body'           => [
                'Thanks for choosing WestJet',
            ],
            'href'   => ['westjet.com'],
            'agency' => ['WestJet'],
        ],
        'wtravel' => [
            // 'iata' => '',
            'isTravelAgency' => true,
            'from'           => [],
            'uniqueSubject'  => [],
            'body'           => [],
            'href'           => [],
            'agency'         => ['WORLD TRAVEL SERVICE'],
        ],
    ];

    public static $rentalProviders = [
        'localiza'     => ['LOCALIZA RENTACAR'],
        'hertz'        => ['HERTZ RENT A CAR'],
        'perfectdrive' => ['BUDGET RENT A CAR'],
        'avis'         => ['AVIS RENT A CAR'],
        'national'     => ['NATIONAL CAR RENTAL'],
        //        '' => [''],
        //        '' => [''],
    ];

    public $reFrom = "@tripcase.com";

    public static $commonSubject = [
        "de" => "Elektronisches E-Ticket",
        "ACHTUNG-FLUGPLAN-AENDERUNG",
        "en" => "Electronic ticket receipt",
        "Travel Reservation",
        "es" => "Reserva de viaje",
        "Recibo de pasaje electrónico,",
        "Recibo de boleto electrónico,",
        "Your itinerary and receipt - ",
        "Confirmación de compra",
        "ru" => "Квитанция об оплате электронного билета",
        "pt" => "Reserva de viagem",
        "pl" => "Rezerwacja",
        "it" => "Prenotazione del viaggio",
    ];

    public $subject;
    public $text;
    public $reBody = ['Tripcase'];
    public $reBody2 = [
        'en' => [
            'Itinerary with TripCase',
            'passenger receipt through TripCase', // it-2429481.eml
            'eInvoice', // it-2429481.eml
            'Thanks for choosing WestJet',
            'Thanks for booking with Bangkok Airways',
            'Thank you for booking with Belavia',
            'Thank you for booking your flight with Gulf Air',
            'Thank you for choosing Aeromexico',
            'Thank you for choosing Pakistan International Airlines',
            'Thank you for choosing PIA',
            'Thank you for choosing Ethiopian',
            'Thank you for choosing to book with ',
            'Reservation code:',
            'Thanks for choosing Aeroflot',
            'Thank you for booking with us!',
            'Mahalo for choosing Hawaiian Airlines.',
            'for purchasing your ticket with Vietnam Airlines',
            'Please verify flight times prior to departure',
        ],
        'fr' => [
            'Gagnez du temps et profitez de votre vol',
            'Message de votre chargé de voyages',
            'Numéro de billets',
        ],
        'de' => [
            'Reiseplan mit Tripcase',
        ],
        'es' => [
            'itinerario con TripCase',
            'Tu compra se realizó con éxito. Gracias por preferir Aeroméxico',
            'Es un placer brindarte el servicio que mereces',
            'Gracias por preferir Aeroméxico',
            'Billete electrónico',
            'Un mensaje de su agente de viajes',
            'Gracias por elegir volar con Aeroméxico',
        ],
        'ru' => [
            'с помощью TripCase',
            'Благодарим Вас за то, что Вы выбрали Аэрофлот',
        ],
        'pt' => [
            'Itinerário com o TripCase',
            'Confirmação da empresa aérea',
            'Viagem para:',
        ],
        'pl' => [
            'Plan podróży',
        ],
        'tr' => [
            'Yolcu(lar):',
        ],
        'vi' => [
            'Khởi hành:',
        ],
    ];
    public $reBodyAlt = [
        "de" => ["Zum Kalender", "Passagier:"],
        "es" => ["Agregar al calendario", "Itinerario"],
        "en" => ["Add to Calendar", "Trip to:"],
        "it" => ["Aggiungi al calendario", "Passeggero:"],
    ];

    public static $dictionary = [
        'bs' => [
            // "Confirmation#" => "",
            // "Date issued:" => "",
            "Confirmed"         => "Potvrđeno",
            // "Itinerary" => "",
            "Reservation code:" => "Šifra rezervacije",
            // "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            // "Frequent flight number:" => "",
            "Your ticket(s) is/are:"    => "Broj karte",
            // "Ticket Number:" => "",
            // "Passenger(s):" => "",
            // "Airline Confirmation:" => "", // in segment
            // "Airline Reservation Code:" => "", // in header
            "Departure:"                => "Polazak:",
            "Arrival:"                  => "Dolazak:",
            // "DEPART" => "",
            // "ARRIVE" => "",
            "Seat:"                => "Sedište:",
            // "Seat(s):" => "",
            "Flight Number"        => "Broj leta",
            // "Aircraft:" => "",
            "Distance (in Miles):" => "Udaljenost (u miljama):",
            // "Class:" => "",
            "Duration:"            => "Trajanje leta:",
            "Meal:"                => "Obrok:",
            "Operated by:"         => "Operativni prevozilac:",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            // "Total:" => "",

            // "PRIX BILLET AVION" => "",

            // HOTEL
            // "Confirmation Number:" => "",
            // "CHECKIN" => "",
            // "CHECKOUT" => "",
            // "PHONE" => "",
            // "FAX" => "",
            // "RATE" => "",
            // "ROOMS" => "",
            // "Termos:" => "",
            // "Room Details:" => "",
            // "Guest(s):" => "",
            // "Room Type:" => "",
            // "Rate Plan:" => "",

            // CAR
            // "Pick Up:" => "",
            // "Drop Off:" => "",
            // "Pick Up At:" => "",
            // "Drop Off At:" => "",
            // "Approximate Total Price" => "",
            // "Car Type:" => "",
            // "Telephone:" => "",
            // "Member ID" => "",

            // EVENT
            // "Tour:" => "",
            // "Tour Code:" => "",
        ],
        'fr' => [
            //            "Confirmation#" => [""],
            //            "Date issued:" => "",
            "Confirmed" => "Confirmé",
            //            "Itinerary" => "",
            "Reservation code:" => ["Numéro de réservation", "Numéro de réservation:", "Reservation", "Reservation code", "Code de réservation"],
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            "Frequent flight number:" => "Voyageur fréquent:",
            //            "Your ticket(s) is/are:" => "",
            //            "Ticket Number:" => "",
            "Passenger(s):"         => ["Passager(s):"],
            "Airline Confirmation:" => ["Code de réservation de la compagnie aérienne:"], // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => ["Départ:"],
            "Arrival:"   => ["Arrivée:"],
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:" => "Siège:",
            //            "Seat(s):" => "",
            "Flight Number"        => "Numéro de vol",
            "Aircraft:"            => "Appareil:",
            "Distance (in Miles):" => "Distance en miles:",
            "Class:"               => ["Classe:", "Cabine:"],
            "Duration:"            => "Durée:",
            "Meal:"                => "Repas:",
            "Operated by:"         => "Exploité par:",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            "PRIX BILLET AVION" => "",

            // HOTEL
            "Confirmation Number:" => "",
            //            "CHECKIN" => "",
            //            "CHECKOUT" => "",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => "",
            //            "ROOMS" => "",
            "Termos:" => ["Détails:"],
            //            "Room Details:" => "",
            //            "Guest(s):" => "",
            "Room Type:" => "Type de chambre:",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            "Tour:"      => "Voyage organisé:",
            "Tour Code:" => "Code voyage:",
        ],
        'de' => [
            "Confirmation#" => ["Bestätigung#", "Reservierungscode:", "Bestätigung #", "Confirmation#"],
            //            "Date issued:" => "",
            "Confirmed"         => "Bestätigt",
            "Itinerary"         => "Reiseplan",
            "Reservation code:" => ["Reservierungscode:", "Booking Reference", "Buchungscode"],
            "next day arrival"  => "Ankunft am folgenden Tag",
            // "taxDetect" => "",

            // FLIGHT
            "Frequent flight number:"   => "Vielfliegerprogrammnummer:",
            "Your ticket(s) is/are:"    => "Your ticket(s) is/are:",
            "Ticket Number:"            => "Ticketnummer:",
            "Passenger(s):"             => ["Passagier(e):", "Passagier:"],
            "Airline Confirmation:"     => "Reservierungsnummer:", // in segment
            "Airline Reservation Code:" => ["Buchungscode der Fluggesellschaft:"], // in header
            "Departure:"                => "Abfluginfo:",
            "Arrival:"                  => "Ankunftsinfo:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:"                => "Sitzplatz:",
            "Seat(s):"             => "Sitzplatz:",
            "Flight Number"        => "Flugnummer",
            "Aircraft:"            => "Flugzeugtyp:",
            "Distance (in Miles):" => "Meilen:",
            "Class:"               => ["Buchungsklasse:", "Kabine:"],
            "Duration:"            => "Dauer:",
            "Meal:"                => "Mahlzeit:",
            "Operated by:"         => "Durchgeführt bei:",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //"Confirmation Number:" => "",
            //            "CHECKIN" => "",
            //            "CHECKOUT" => "",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => "",
            //            "ROOMS" => "",
            //            "Termos:" => "",
            //            "Room Details:" => "",
            //            "Guest(s):" => "",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'es' => [
            "Confirmation#"     => ["Confirmation#", "Confirmación#", "Confirmation #", "Confirmación #"],
            "Date issued:"      => "Fecha de emisión:",
            "Confirmed"         => "Confirmado",
            "Itinerary"         => ["Itinerario", "Billete electrónico"],
            "Reservation code:" => ["Código de reservación:", "Código de Reservación", "Código de reserva", "Código de Reserva", "Reservation code", "Confirmación #", 'Booking Reference'],
            "next day arrival"  => "arribo al día siguiente",
            // "taxDetect" => "",

            // FLIGHT
            "Frequent flight number:"   => ["Pasajero frecuente:", "Núm. de pasajero frecuente:"],
            "Your ticket(s) is/are:"    => ["Tu número de ticket es:", "Su(s) boleto(s):", "Tu(s) boleto(s):"],
            "Ticket Number:"            => "Número de pasaje:",
            "Passenger(s):"             => ["Pasajero/s:", "Pasajero:"],
            "Airline Confirmation:"     => "Confirmación de aerolínea:", // in segment
            "Airline Reservation Code:" => "Código de reserva de la aerolínea:", // in header
            "Departure:"                => "Salida:",
            "Arrival:"                  => "Llegada:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:"                => "Asiento:",
            "Seat(s):"             => "Asiento:",
            "Flight Number"        => "Número de vuelo",
            "Aircraft:"            => "Aeronave:",
            "Distance (in Miles):" => ["Millaje:", "Distancia (en Millas):"],
            "Class:"               => ["Clase:", "Cabina:"],
            "Duration:"            => "Duración:",
            "Meal:"                => "Comida:",
            "Operated by:"         => "Operado por:",
            "Fare" => "Tarifa",
            // "fareEquivalent" => "",
            "feeRowStart" => "Impuestos/tasas/cargos",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "",
            "CHECKIN"       => "Entrada:",
            "CHECKOUT"      => "Salida:",
            "PHONE"         => "Ph:",
            "FAX"           => "Fax:",
            "RATE"          => "Tarifa:",
            "ROOMS"         => "Habitación(es):",
            "Termos:"       => ["Termos:", "Terms:", "Remarks:", "Términos:"],
            "Room Details:" => "Detalles de las habitaciones:",
            "Guest(s):"     => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            "Pick Up At:"             => "Retirar en:",
            "Drop Off At:"            => "Devolver en:",
            "Approximate Total Price" => ["Precio total aprox.", "Precio Total Aproximado:"],
            "Car Type:"               => "Tipo de auto:",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'ru' => [
            "Confirmation#"     => ["Подтверждение#", "Подтверждение #"],
            "Date issued:"      => "Дата выдачи:",
            "Confirmed"         => "ПОДТВЕРЖДЕНО",
            "Itinerary"         => "Маршрут",
            "Reservation code:" => ["Код предварительного заказа:", "Код бронирования", "Код бронирования:"],
            //            "next day arrival" => "",
            "taxDetect" => "Налоги",

            // FLIGHT
            "Frequent flight number:"   => ["Постоянный клиент авиакомпании:", "Номер участника программы лояльности:"],
            "Your ticket(s) is/are:"    => "Ваш(и) билет(ы):",
            "Ticket Number:"            => "Номер билета:",
            "Passenger(s):"             => ["Пассажир(ы):", "Пассажир:"],
            "Airline Confirmation:"     => "Подтверждение авиакомпании:", // in segment
            "Airline Reservation Code:" => "Код предварительного заказа авиакомпании:", // in header
            "Departure:"                => "Отправление:",
            "Arrival:"                  => "Прибытие:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:"                => "Место:",
            "Seat(s):"             => "Место:",
            "Flight Number"        => "Номер рейса",
            "Aircraft:"            => "Самолет:",
            "Distance (in Miles):" => "Расстояние (в милях):",
            "Class:"               => ["Класс:", "Салон:"],
            "Duration:"            => "Продолжительность:",
            "Meal:"                => "Питание:",
            //            "Operated by:" => "",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            "Total:" => "Общая стоимость:",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "NOTTRANSLATED",
            "CHECKIN"       => "Время и дата заезда:",
            "CHECKOUT"      => "Время и дата выезда:",
            "PHONE"         => ["Phone:", "Ph:"],
            "FAX"           => "Факс:",
            "RATE"          => "Тариф:",
            "ROOMS"         => "Номер(а):",
            "Termos:"       => ["Terms:", "Remarks:"],
            "Room Details:" => "Информация о номере:",
            "Guest(s):"     => "Гость(-и):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'pt' => [
            "Confirmation#" => ["Confirmation#", "Confirmação#", "Confirmation #", "Confirmação #"],
            //            "Date issued:" => "",
            "Confirmed"         => "Confirmado",
            "Itinerary"         => ["Itinerário", "eTicket"],
            "Reservation code:" => ["Código de reserva:", "Reservation code", "Codigo da reserva", "Reserva #"],
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            "Frequent flight number:"   => ["Número de viajante freqüente:", "Viajante freqüente:"],
            "Your ticket(s) is/are:"    => "Your ticket(s) is/are:",
            "Ticket Number:"            => "Número do bilhete:",
            "Passenger(s):"             => ["Passageiro(s):", "Passageiro:"],
            "Airline Confirmation:"     => "Confirmação da empresa aérea:", // in segment
            "Airline Reservation Code:" => "Localizador da Reserva:", // in header
            "Departure:"                => "Partida:",
            "Arrival:"                  => "Chegada:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:"                => "Assento:",
            "Seat(s):"             => "Assento:",
            "Flight Number"        => ["Número do vôo", "Número do voo"],
            "Aircraft:"            => "Aeronave:",
            "Distance (in Miles):" => ["Milhagem:", "Distância (em milhas):"],
            "Class:"               => ["Classe:", "Cabine:"],
            "Duration:"            => "Duração:",
            "Meal:"                => "Refeição:",
            "Operated by:"         => "Operado por:",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            "Total:"               => "Total:",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "NOTTRANSLATED",
            "CHECKIN"  => "Check-in:",
            "CHECKOUT" => "Check-out:",
            "PHONE"    => "Ph:",
            "FAX"      => "Fax:",
            //            "RATE" => "NOTTRANSLATED",
            "ROOMS"         => "Quarto(s):",
            "Termos:"       => ["Terms:", "Remarks:"],
            "Room Details:" => "Detalhes do Quarto:",
            "Guest(s):"     => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            "Pick Up At:"             => ["Pick-up at:"],
            "Drop Off At:"            => ["Drop-off at:"],
            "Approximate Total Price" => "Preço Total Aproximado:",
            "Car Type:"               => ["Tipo de Carro:"],
            "Telephone:"              => "Telefone:",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'pl' => [
            "Confirmation#" => ["Potwierdzenie#", "Potwierdzenie #"],
            "Date issued:"  => "Data wystawienia:",
            "Confirmed"     => "Potwierdzono",
            //            "Itinerary" => "",
            "Reservation code:" => "Kod rezerwacji:",
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            //            "Frequent flight number:" => "",
            //            "Your ticket(s) is/are:" => "",
            "Ticket Number:"        => "Numer biletu:",
            "Passenger(s):"         => ["Pasażerowie:", "Pasażer:"],
            "Airline Confirmation:" => ["Kod rezerwacji linii lotniczej:", "Potwierdzenie linii lotniczych:"], // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => "Wylot:",
            "Arrival:"   => "Przylot:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:" => "Miejsce:",
            //            "Seat(s):" => "",
            "Flight Number"        => "Numer lotu",
            "Aircraft:"            => "Samolot:",
            "Distance (in Miles):" => "Odległość w milach:",
            "Class:"               => ["Klasa:", "Kabina:"],
            "Duration:"            => "Czas trwania:",
            "Meal:"                => "Posiłek:",
            //            "Operated by:" => "",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "NOTTRANSLATED",
            "CHECKIN"  => "Zameldowanie:",
            "CHECKOUT" => "Wymeldowanie:",
            //            "PHONE" => "",
            //            "FAX" => "",
            "RATE"          => "Stawka:",
            "ROOMS"         => "Pokój(-oje):",
            "Termos:"       => ["Terms:", "Remarks:"],
            "Room Details:" => "Szczegóły pokoju:",
            "Guest(s):"     => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'tr' => [
            "Confirmation#" => ["Konfirmasyon#"],
            //            "Date issued:" => "",
            //            "Confirmed" => "",
            //            "Itinerary" => "",
            "Reservation code:" => "Rezervasyon kodu:",
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            //            "Frequent flight number:" => "",
            //            "Your ticket(s) is/are:" => "",
            //            "Ticket Number:" => "",
            "Passenger(s):"         => "Yolcu(lar):",
            "Airline Confirmation:" => "Havayolu Rezervasyon Kodu:", // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => "Hareket:",
            "Arrival:"   => "Varış:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            //            "Seat:" => "",
            //            "Seat(s):" => "",
            "Flight Number"        => "Uçuş Numarası",
            "Aircraft:"            => "Uçak:",
            "Distance (in Miles):" => "Mesafe (Mil):",
            "Class:"               => "Kabin:",
            "Duration:"            => "Süre:",
            "Meal:"                => "Yemek:",
            //            "Operated by:" => "",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "",
            //            "CHECKIN" => ":",
            //            "CHECKOUT" => ":",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => ":",
            //            "ROOMS" => ":",
            //            "Termos:" => ["Terms:", "Remarks:"],
            //            "Room Details:" => ":",
            //            "Guest(s):" => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'vi' => [
            "Confirmation#" => ["Xác nhận#"],
            //            "Date issued:" => "",
            "Confirmed" => "Đã xác nhận",
            //            "Itinerary" => "",
            "Reservation code:" => "Mã đặt chỗ",
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            //            "Frequent flight number:" => "",
            "Your ticket(s) is/are:" => "Số vé:",
            "Ticket Number:"         => "Số vé:",
            //            "Passenger(s):" => "",
            //            "Airline Confirmation:" => "", // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => "Khởi hành:",
            "Arrival:"   => "Giờ đến:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:" => "Chỗ ngồi:",
            //            "Seat(s):" => "",
            "Flight Number"        => "Số hiệu chuyến bay",
            "Aircraft:"            => "Máy bay:",
            "Distance (in Miles):" => "Khoảng cách (Dặm):",
            "Class:"               => "Hạng dịch vụ:",
            "Duration:"            => "Thời gian bay:",
            "Meal:"                => "Bữa ăn:",
            //            "Operated by:" => "",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "",
            //            "CHECKIN" => ":",
            //            "CHECKOUT" => ":",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => ":",
            //            "ROOMS" => ":",
            //            "Termos:" => ["Terms:", "Remarks:"],
            //            "Room Details:" => ":",
            //            "Guest(s):" => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'it' => [
            "Confirmation#"     => ["Conferma#"],
            "Date issued:"      => "Data di emissione:",
            "Confirmed"         => "Confermato",
            "Itinerary"         => ["Biglietto elettronico", "Itinerario"],
            "Reservation code:" => ["Codice di Prenotazione", "Codice di prenotazione:", "Booking Reference"],
            //            "next day arrival" => "",
            "taxDetect" => "Tasse ",

            // FLIGHT
            //            "Frequent flight number:" => "",
            "Your ticket(s) is/are:" => "Your ticket(s) is/are:",
            "Ticket Number:"         => "Numero biglietto:",
            "Passenger(s):"          => ["Passeggero:", "Passeggero/i:"],
            "Airline Confirmation:"  => "Conferma compagnia aerea:", // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => "Partenza:",
            "Arrival:"   => "Arrivo:",
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Seat:"                => "Posto:",
            "Seat(s):"             => "Posto:",
            "Flight Number"        => "Numero volo",
            "Aircraft:"            => "Aeromobile:",
            "Distance (in Miles):" => ["Distanza (in miglia):", "Migliaggio:"],
            "Class:"               => ["Classe:", "Cabina:"],
            "Duration:"            => "Durata:",
            "Meal:"                => "Pasto:",
            "Operated by:"         => "Gestito da:",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            "Total:"               => "Totale:",

            //            "PRIX BILLET AVION" => "",

            // HOTEL
            //            "Confirmation Number:" => "",
            //            "CHECKIN" => ":",
            //            "CHECKOUT" => ":",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => ":",
            //            "ROOMS" => ":",
            //            "Termos:" => ["Terms:", "Remarks:"],
            //            "Room Details:" => ":",
            //            "Guest(s):" => "Guest(s):",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            "Pick Up At:"  => "Ritiro presso:",
            "Drop Off At:" => "Consegna presso:",
            //            "Approximate Total Price" => "",
            "Car Type:" => "Tipo di auto:",
            //            "Telephone:" => "",
            //            "Member ID" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'sv' => [
            //            "Confirmation#" => [""],
            //            "Date issued:" => [""],
            "Confirmed" => "BEKRÄFTAD",
            //            "Itinerary" => [""],
            "Reservation code:" => ["Booking Reference"],
            //            "next day arrival" => "",
            // "taxDetect" => "",

            // FLIGHT
            //            "Frequent flight number:" => [""],
            "Your ticket(s) is/are:" => ["Your ticket(s) is/are:"],
            //            "Ticket Number:" => "",
            //            "Passenger(s):" => [""],
            //            "Airline Confirmation:" => "", // in segment
            //            "Airline Reservation Code:" => "", // in header
            "Departure:" => ["Avresa:"],
            //            "DEPART" => "",
            //            "ARRIVE" => "",
            "Arrival:"             => ["Ankomst:"],
            "Seat:"                => ["Sittplats:"],
            "Seat(s):"             => "Sittplats:",
            "Flight Number"        => ["Flygnummer"],
            "Aircraft:"            => ["Flygplan:"],
            "Distance (in Miles):" => "Körsträcka i miles:",
            "Class:"               => ["Kabin:"],
            "Duration:"            => ["Längd:"],
            "Meal:"                => ["Måltid:"],
            //            "Operated by:" => "",
            // "Fare" => "",
            // "fareEquivalent" => "",
            // "feeRowStart" => "",
            //            "Total:" => "",

            //            "PRIX BILLET AVION" => "",

            //            "Member ID" => "Member ID",
            // HOTEL
            //            "CHECKIN" => "",
            //            "CHECKOUT" => "",
            //            "PHONE" => "",
            //            "FAX" => "",
            //            "RATE" => "",
            //            "ROOMS" => "",
            //            "Termos:" => "",
            //            "Room Details:" => "",
            //            "Guest(s):" => "",
            //            "Room Type:" => "",
            //            "Rate Plan:" => "",

            // CAR
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Pick Up At:" => "",
            //            "Drop Off At:" => "",
            //            "Approximate Total Price" => "",
            //            "Car Type:" => "",
            //            "Telephone:" => "",

            // EVENT
            //            "Tour:" => "",
            //            "Tour Code:" => "",
        ],
        'en' => [ // `en` always last!!!
            "Confirmation#"      => ["Confirmation#", "Confirmation #"],
            "Date issued:"       => ["Date issued:", "Invoice Issue Date:"],
            "Confirmed"          => ["Confirmed", 'Waitlisted'],
            "Waitlisted"         => 'Waitlisted',
            "Itinerary"          => ["Itinerary", "eTicket"],
            "Reservation code:"  => ["Reservation code:", "Reservation code", "Your Reservation Code", "Reservation", "Booking reference", "WestJet reservation code", "Your Booking Reference is",
                "Reservation Code:", "Abacus Reservation Code:", "Reservation code / Code Booking", "Record Locator:", "Booking Reference", "Confirmation #",
                "Your flight reservation number:", 'Confirmation Code', ],
            "next day arrival" => "next day arrival",
            // "taxDetect" => "",

            // FLIGHT
            "Frequent flight number:" => ["Frequent flight number:", "Frequent flyer number:", "Frequent Flyer:", "Corporate Discount #:"],
            "Your ticket(s) is/are:"  => ["Your ticket(s) is/are:", "Your E-ticket(s) is/are:", "Your E-Ticket Number/Receipt is:", "Your ticket(s) is/are :",
                'E-Ticket Nbr', "Passenger name(s) and ticket number(s):", "Your eTicket receipt can be found here:", ],
            "Ticket Number:"            => "Ticket Number:",
            "Passenger(s):"             => ["Passenger(s):", "Passenger:"],
            "Airline Confirmation:"     => "Airline Confirmation:", // in segment
            "Airline Reservation Code:" => "Airline Reservation Code:", // in header
            "Departure:"                => ["Departure:", "From:"],
            "DEPART"                    => "DEPART",
            "ARRIVE"                    => "ARRIVE",
            "Arrival:"                  => ["Arrival:", "To:"],
            "Seat:"                     => ["Seat:", "Space:"],
            "Seat(s):"                  => "Seat(s):",
            "Flight Number"             => ["Flight Number"],
            "Aircraft:"                 => ["Aircraft:", "Type:"],
            "Distance (in Miles):"      => "Distance (in Miles):",
            "Class:"                    => ["Class:", "Cabin:"],
            "Duration:"                 => ["Duration:", "Flying Time:"],
            "Meal:"                     => ["Meal:"],
            "Operated by:"              => "Operated by:",
            // "Fare" => "",
            "fareEquivalent" => "Equivalent amount paid",
            "feeRowStart" => "Taxes/Fees/Carrier",
            "Total:"                    => "Total:",

            //            "PRIX BILLET AVION" => "",

            //            "Member ID" => "Member ID",
            // HOTEL
            "Confirmation Number:" => ["Confirmation Number:", "Confirmation:"],
            "CHECKIN"              => ["Check-In:", "Check In:"],
            "CHECKOUT"             => ["Check-Out:", "Check Out:"],
            "PHONE"                => ["Phone:", "Ph:"],
            "FAX"                  => "Fax:",
            "RATE"                 => ["Rate per Night:", "Rate:"],
            "ROOMS"                => ["Rooms(s):", "Room(s):"],
            "Termos:"              => ["Terms:", "Remarks:"],
            "Room Details:"        => "Room Details:",
            "Guest(s):"            => "Guest(s):",
            "Room Type:"           => "Room Type:",
            "Rate Plan:"           => "Rate Plan:",

            // CAR
            "Pick Up:"                => "Pick Up:",
            "Drop Off:"               => "Drop Off:",
            "Pick Up At:"             => ["Pick Up At:", "Pick-up at:", "Pick-up at"],
            "Drop Off At:"            => ["Drop Off At:", "Drop-off at:", "Drop-off at"],
            "Approximate Total Price" => ["Approximate Total Price", "Approx Total Price", "Approx. Total Price:"],
            "Car Type:"               => ["Car Type:", "Class:"],
            "Telephone:"              => ["Telephone:", "Phone"],

            // EVENT
            "Tour:"      => "Tour:",
            "Tour Code:" => "Tour Code:",
        ],
    ];

    public $lang = "en";

    private $date = null;
    private $providerCode = null;

    private $airCodeIsPartCityName = [
        'ABU',
        'AIN',
        'ANA',
        'ANN',
        'ARM',
        'AUA',
        'AUE',
        'AUR',
        'AVU',
        'BAR',
        'BAY',
        'BEN',
        'BIG',
        'BOA',
        'BOM',
        'BOW',
        'CAN',
        'CAP',
        'CAR',
        'CAT',
        'CAY',
        'CON',
        'CUT',
        'DAM',
        'DAO',
        'DAR',
        'DAY',
        'DEL',
        'DEN',
        'DES',
        'DOC',
        'DOG',
        'DOM',
        'DOS',
        'DUC',
        'DUN',
        'EAU',
        'EIN',
        'ELK',
        'END',
        'EVA',
        'FAK',
        'FIN',
        'FOX',
        'GAG',
        'GAH',
        'GAL',
        'GAN',
        'GAP',
        'GUA',
        'HAI',
        'HAO',
        'HAT',
        'HAY',
        'HIN',
        'HOA',
        'HOI',
        'HOT',
        'HOY',
        'HUA',
        'ICY',
        'IDA',
        'ILE',
        'INE',
        'ISA',
        'KAR',
        'KEY',
        'KOH',
        'KOT',
        'LAC',
        'LAI',
        'LAS',
        'LAU',
        'LAY',
        'LEA',
        'LES',
        'LOP',
        'LOS',
        'LOW',
        'MAE',
        'MAI',
        'MAN',
        'MAR',
        'MAU',
        'MAY',
        'MEI',
        'MIA',
        'MOZ',
        'MYS',
        'NAW',
        'NEW',
        'NHA',
        'OAK',
        'OIL',
        'OKI',
        'OLD',
        'ONO',
        'ORD',
        'ORO',
        'OUM',
        'OYA',
        'PAA',
        'PAF',
        'PAN',
        'PAS',
        'PAU',
        'PAZ',
        'PHI',
        'PHU',
        'PUY',
        'RAE',
        'RAI',
        'RAM',
        'RAS',
        'RED',
        'REI',
        'ROG',
        'ROI',
        'ROY',
        'RUM',
        'SAI',
        'SAM',
        'SAN',
        'SEA',
        'SEO',
        'SHI',
        'SIR',
        'SOC',
        'SON',
        'SOT',
        'SUE',
        'SUI',
        'SUL',
        'SUN',
        'SUR',
        'TAN',
        'TAR',
        'TAU',
        'TEL',
        'THE',
        'THO',
        'TIN',
        'TOM',
        'TON',
        'TUY',
        'ULA',
        'ULM',
        'VAL',
        'VAN',
        'WAA',
        'WAD',
        'XAI',
        'XAY',
        'YAI',
        'YAM',
        'YAN',
        'YES',
        'YEU',
    ];

    public function parseHtml(Email $email)
    {
        $xpathBold = "(self::b or self::strong or contains(@style,'bold'))";

        $namePrefixes = ['MASTER', 'Miss', 'Mstr', 'Mrs', 'Mr', 'Ms', 'Dr', 'MS', 'MRS', 'MR'];

        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        // Travel Agency
        $company = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Itinerary")) . " and count(ancestor::tr[1]/td) = 2]/preceding-sibling::td[1][.//img]//text()[normalize-space()])[1]");

        if (empty($company)) {
            $company = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Itinerary")) . " and count(ancestor::tr[1]/td) = 2]/preceding-sibling::td[1][not(.//preceding::" .
                "text()[" . $this->contains($this->t("Reservation code:")) . " or " . $this->contains($this->t("Date issued:")) . " or " . $this->contains($this->t("Departure:")) . "]) ]//text()[normalize-space()])[1]");
        }

        if (!empty($company)) {
            foreach (self::$detectProviders as $code => $params) {
                if (!empty($params['agency']) && $params['isTravelAgency'] == true) {
                    foreach ($params['agency'] as $fText) {
                        if (preg_match("#\b" . $this->opt($fText) . "\b#", $company)) {
                            $this->providerCode = $code;
                        }
                    }
                }
            }
        }

        $reservationCodes = array_filter(array_unique(array_filter($this->http->FindNodes(".//text()[" . $this->eq($this->t("Reservation code:")) . "]/following::text()[string-length()>3][1]",
            null, "#^(\w+)$#"))));

        if (empty($reservationCodes)) {
            $reservationCodes = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Reservation code:")) . "]/ancestor::td[1][not(.//*[normalize-space()][not(contains(., ':'))]) and descendant::text()[normalize-space()][1][" . $this->eq($this->t("Reservation code:")) . "]]/following-sibling::td[1]/descendant::text()[normalize-space()][1]",
                null, "#^\w{5,7}$#")));
        }

        if (empty($reservationCodes)) {
            $reservationCodes = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Reservation code:")) . "]",
                null, "/" . $this->opt($this->t("Reservation code:")) . "\s*\b([A-Z\d]{5,7})\s*$/")));
        }

        if (empty($this->providerCode)) {
            $this->providerCode = 'tcase';
        }

        if (!empty($this->providerCode) && !empty(self::$detectProviders[$this->providerCode]['isTravelAgency'])) {
            foreach ($reservationCodes as $reservationCode) {
                $email->ota()
                    ->confirmation($reservationCode);
            }
        }

        $patterns['time'] = '(?:\b\d{4}\b|\d{1,2}(?::\d{2})?(?:\s*[AaPp](?:\.[ ]*)?[Mm]\.?)?)';

        $reservationXpath = "//text()[" . $this->eq($this->t("Reservation code:")) . "]/ancestor::*[.//*[" . $this->eq($this->t("Departure:")) . "]][1]";
        $this->logger->info('$reservationXpath:');
        $this->logger->debug($reservationXpath);
        $resNodes = $this->http->XPath->query($reservationXpath);

        if ($resNodes->length === 0) {
            $reservationXpath = ".";
            $resNodes = $this->http->XPath->query($reservationXpath);
        }

        foreach ($resNodes as $resNode) {
            $travellers = $this->http->FindNodes("descendant::*[ (contains(@id,'firstLastName') or contains(@class,'firstLastName')) and ancestor-or-self::*[{$xpathBold}] ]", $resNode, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if (in_array(null, $travellers, true)) {
                $travellers = null;
            }

            if (empty($travellers)) {
                $travellers = $this->http->FindNodes(".//td[not(.//td) and " . $this->eq($this->t("Passenger(s):")) . "]/following::td[1]//text()[normalize-space()]",
                    $resNode, "#^\s*([^\[]+?)\s*(?:\[|$)#");
            }

            if (empty($travellers)) {
                $travellers = $this->http->FindNodes(".//text()[" . $this->eq($this->t("Passenger(s):")) . "]/ancestor::td[1]/following::td[1]//text()[normalize-space()]",
                    $resNode, "#^\s*([^\[]+?)\s*(?:\[|$)#");
                array_shift($travellers);
            }

            // Mr​ Waris Khan
            // M​r Car​loshu​mberto Gar​ciaal​varado
            if (empty($travellers)) {
                $travellers = array_unique($this->http->FindNodes(".//*[{$this->eq($this->t("Seat(s):"))} or {$this->eq($this->t("Seat:"))}]/preceding::td[1]",
                    $resNode, "#^.{2,20}\s+.{2,20}$#iu"));
            }

            if (empty($travellers)) {
                $travellers = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t("Your ticket(s) is/are:"))}]/ancestor::table[1]/descendant::tr/td/b[not({$this->contains($this->t("Your ticket(s) is/are:"))})]", $resNode, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[\s:]*$/u'));
            }

            $travellers = preg_replace('/[?]/', '', $travellers);
            $travellers = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', $travellers);
            $travellers = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', $travellers);
            $travellers = array_values(array_unique(array_filter($travellers)));

            //#################
            //##    AIRS    ###
            //#################

            if ($date = $this->nextText($this->t("Date issued:"), $resNode)) {
                $this->date = $this->normalizeDate($date);
            } elseif (stripos($this->text, 'TRIP TO') !== false && preg_match("/^(\d{1,2}\s*[[:alpha:]]+\s*\d{4})\s+\d{1,2}\s*[[:alpha:]]+\s*\d{4}\s*TRIP\s+TO\s+/u", $this->text, $m)) {
                $this->date = $this->normalizeDate($m[1]);
            }
            $xpathFlightFilter = "[not(descendant::tr[not(.//tr[normalize-space()]) and normalize-space()][1]/descendant::text()[{$this->eq($this->t("OPEN"))}])]";
            $xpathFlight = "descendant::text()[{$this->eq($this->t("Departure:"))}]/ancestor::tr[following-sibling::tr[normalize-space()]][1]/following-sibling::tr[descendant::text()[{$this->eq($this->t("Arrival:"))}] and string-length(normalize-space())>13]/ancestor-or-self::tr[ancestor::*[1][{$this->contains($this->t("Class:"))} or {$this->contains($this->t("Meal:"))} or {$this->contains($this->t("Seat:"))}] or contains(.,'Departure')][1]/ancestor::*[1][not(preceding::img[normalize-space(@src)][1][{$this->contains(['icon-rail', 'icon-bus'], '@src')}]) and not({$this->contains(['Ship:', 'Bus:'], '@src')})]" . $xpathFlightFilter . "[not(contains(normalize-space(), 'Ship'))]";
            //$this->logger->debug('$xpathFlight = ' . $xpathFlight);
            $nodes = $this->http->XPath->query($xpathFlight, $resNode);

            if ($nodes->length === 0 && $this->http->XPath->query(".//img[contains(@src,'/icon-air.')]", $resNode)->length > 0) {
                $this->logger->debug("Found flights but not selected");
                $email = null;

                return;
            }

            if ($nodes->length > 0) {
                $isInvalidCruise = [];
                $pax = $travellers;
                $f = $email->add()->flight();

                if (empty($email->getTravelAgency()) && count($reservationCodes) > 0) {
                    foreach ($reservationCodes as $reservationCode) {
                        $f->general()->confirmation($reservationCode);
                    }
                } else {
                    $f->general()->noConfirmation();
                }

                $itTicketNumbers = [];
                $itPassengers = [];
                $itAccountNumbers = [];
//        foreach ($airs as $rl => $roots) {

                foreach ($nodes as $root) {
                    $s = $f->addSegment();

                    $xpathHeader = "ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[{$this->contains($this->t("Reservation code:"))} or {$this->contains($this->t("Passenger(s):"))}][1]";
                    $xpathFooter = "ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[{$this->contains($this->t("Total:"))}][1]";

                    // status
                    if (preg_match("/\b({$this->opt($this->t("Confirmed"))})\b/iu",
                        $this->http->FindHTMLByXpath('.', null, $root), $m)) {
                        $s->setStatus($m[1]);
                    }

                    // ticketNumber
                    $ticketNumber = $this->http->FindSingleNode($xpathHeader . "/descendant::text()[{$this->eq($this->t("Ticket Number:"))}]/following::text()[normalize-space()][1]",
                        $root, true, "/^{$patterns['eTicket']}$/");

                    if ($ticketNumber) {
                        $itTicketNumbers[] = $ticketNumber;
                    }

                    // passengers
                    $passengers = $this->http->FindNodes($xpathHeader . "/descendant::text()[{$this->eq($this->t("Passenger(s):"))}]/ancestor::td[1]/following::td[1]//text()[normalize-space()]",
                        $root, '/^[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]$/u');
                    $passengers = array_filter($passengers);

                    if (count($passengers)) {
                        $itPassengers = array_merge($itPassengers, $passengers);
                    }

                    $this->logger->debug(var_export($passengers, true));

                    $dateDep = $dateArr = null;

                    $dateStr = $this->http->FindSingleNode("tr[1]/td[2]/descendant::text()[normalize-space()][1]", $root)
                        ?? $this->http->FindSingleNode("tr[1]/following::text()[contains(normalize-space(),'-')][1]", $root) ?? '';

                    $dateStr2 = $this->http->FindSingleNode("tr[1]/td[2]/descendant::text()[normalize-space()][2]", $root) ?? '';

                    $dateStr = preg_replace("/^.*{$this->opt($this->t("Departure:"))}\s*([^:\s].*)$/i", '$1', $dateStr);
                    $dateStr2 = preg_replace("/^.*{$this->opt($this->t("Arrival:"))}\s*([^:\s].*)$/i", '$1', $dateStr2);

                    if (preg_match("/-$/", $dateStr)) {
                        $dateDep = $this->normalizeDate(rtrim($dateStr, '- '));
                        $dateArr = $this->normalizeDate($dateStr2);

                        if (empty($dateArr)) {
                            $dateArr = $this->normalizeDate($this->http->FindSingleNode("tr[1]/following::text()[contains(normalize-space(),'-')][1]/following::text()[normalize-space()][1]", $root));
                        }
                    } else {
                        $dateDep = $this->normalizeDate($dateStr);
                        $dateArr = $this->normalizeDate($dateStr2);

                        if (empty($dateArr)) {
                            $dateArr = $dateDep;
                        }
                    }

                    // FlightNumber
                    if (!($flightNumber = $this->nextText($this->t("Flight Number"), $root, 1, '/\b(\d+)\b/'))) {
                        if (!($flightNumber = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]",
                            $root, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/'))) {
                            $flightNumber = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]",
                                $root, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/');
                        }
                    }

                    if (empty($flightNumber)) {
                        $flightNumber = $this->nextText($this->t("Flight Number"), $root, 1,
                            '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/');
                    }

                    if (empty($flightNumber)) {
                        $flightNumber = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'ARRIVE')][1]",
                            $root, true, '/ARRIVE \d{3,4}[AP] \\/ [\w \.]+ (\d{1,5}) RECORD LOCATOR/');
                    }

                    if (empty($flightNumber)) {
                        $flightNumber = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.), 'FLIGHT')][1]",
                            $root, true, '/FLIGHT\s*(\d+)\s+/');
                    }

                    if (!empty($flightNumber)) {
                        $s->airline()
                            ->number($flightNumber);
                    }

                    $airlineNameFullText = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and descendant::text()[{$this->eq($this->t("Flight Number"))}]]", $root);

                    if (preg_match("/^(.{2,}?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*{$this->opt($this->t("Flight Number"))}\s*\d/", $airlineNameFullText, $m)
                        || preg_match("/^(.{2,}?)\s*{$this->opt($this->t("Flight Number"))}\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d/", $airlineNameFullText, $m)
                    ) {
                        // AEROFLOT SU Номер рейса 2659    |    ETHIOPIAN AIRLINES Flight Number ET 145
                        $airlineNameFull = $m[1];
                    } else {
                        $airlineNameFull = null;
                    }

                    if (!($airlineName = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]",
                        $root, true, '/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+$/'))) {
                        if (!($airlineName = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]",
                            $root, true, '/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+$/'))) {
                            $airlineName = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]",
                                $root, true, '/\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])$/');
                        }
                    }

                    if (!$airlineName) {
                        $airlineName = $this->nextText($this->t("Flight Number"), $root, 1,
                            '/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+$/');
                    }

                    $s->airline()->name($airlineName);

                    $rl = $this->http->FindSingleNode(".//text()[{$this->contains($this->t("Confirmation#"))} or {$this->contains($this->t("Airline Confirmation:"))}][1]",
                        $root, true, "/(?:{$this->opt($this->t("Confirmation#"))}|{$this->opt($this->t("Airline Confirmation:"))})\s*([A-Z\d]{5,10})\s*$/iu");

                    if (empty($rl) && !empty($s->getAirlineName())) {
                        $rl = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Airline Reservation Code:")) . "]/ancestor::td[1][not(.//text()[normalize-space()][not(contains(., ':'))]) and descendant::text()[" . $this->eq($this->t("Airline Reservation Code:")) . "]]/following-sibling::td[1]",
                            $resNode, true, "#\b(\w{5,7})\s?\(" . $s->getAirlineName() . "\)#");
                    }

                    if (!empty($rl)) {
                        $s->airline()->confirmation($rl);
                    }

                    // Seats
                    $seatsTravellers = [];

                    $seatXpath = ".//text()[" . $this->eq($this->t("Seat:")) . "]";

                    $seats = $seatsTravellers = [];

                    foreach ($this->http->XPath->query($seatXpath, $root) as $sRoot) {
                        $seat = $this->http->FindSingleNode("following::node()[normalize-space(.)][1]", $sRoot, true, "#^\s*(\d{1,3}[A-Z])\s*(\s+|\/|$)#");

                        if (empty($seat)) {
                            continue;
                        }
                        $traveller = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $sRoot, true, "/^\s*[[:alpha:]][[:alpha:] \-\'\.]+[[:alpha:]]\s*$/");
                        $seats[] = $seat;
                        $seatsTravellers[] = ['seat' => $seat, 'traveller' => $traveller];
                    }

                    if (empty($seats)) {
                        $seatXpath = ".//text()[" . $this->eq($this->t("Seat(s):")) . "]";

                        $seats = $seatsTravellers = [];

                        foreach ($this->http->XPath->query($seatXpath, $root) as $sRoot) {
                            $seat = $this->http->FindSingleNode("following::node()[normalize-space(.)][1]", $sRoot, true, "#^\s*(\d{1,3}[A-Z])(\s+|$)#");

                            if (empty($seat)) {
                                continue;
                            }
                            $traveller = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $sRoot);
                            $seats[] = $seat;
                            $seatsTravellers[] = ['seat' => $seat, 'traveller' => $traveller];
                        }
                    }

                    if (empty($seats)) {
                        $seatXpath = ".//text()[" . $this->starts($this->t("Seat(s):")) . "]";

                        $seats = $seatsTravellers = [];

                        foreach ($this->http->XPath->query($seatXpath, $root) as $sRoot) {
                            $seat = $this->http->FindSingleNode(".", $sRoot, true, "#:\s*(\d{1,3}[A-Z])(\s+|$)#");

                            if (empty($seat)) {
                                continue;
                            }
                            $traveller = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $sRoot);
                            $seats[] = $seat;
                            $seatsTravellers[] = ['seat' => $seat, 'traveller' => $traveller];
                        }
                    }

                    if (empty($seats)) {
                        $seats = array_filter($this->http->FindNodes(".//text()[" . $this->eq($this->t("Seat(s):")) . "]/ancestor::tr[1]",
                            $root, "#:\s*(\d{1,3}[A-Z])(?:\s+|$)#"));
                    }

                    if (empty($seats)) {
                        $seats = array_filter($this->http->FindNodes("./descendant::*[contains(text(),'" . $this->t("Seat(s):") . "')]/following-sibling::*[1]",
                            $root, '/^(\d{1,2}[A-Z])/'));
                    }

                    if (empty($seats)) {
                        $seatsText = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(),'/SEAT ')]",
                            $root, true, '/SEAT ([\dA-Z ]+)/');

                        if (!empty($seatsText)) {
                            $seatsV = array_filter(array_map('trim', preg_split('#(?: |(?<!\d))#', $seatsText)));
                            $seats = [];
                            $number = '';

                            foreach ($seatsV as $value) {
                                if (preg_match("#^[A-Z]$#", $value) && !empty($number)) {
                                    $seats[] = $number . $value;

                                    continue;
                                }

                                if (preg_match("#^(\d{1,3})[A-Z]$#", $value, $m)) {
                                    $seats[] = $value;
                                    $number = $m[1];

                                    continue;
                                }
                                $seats = [];
                                $this->logger->debug("parse seat is failed");

                                break;
                            }
                        }
                    }

                    if (!empty($seatsTravellers)) {
                        foreach ($seatsTravellers as $v) {
                            $s->extra()
                                ->seat($v['seat'], true, true, ucwords(strtolower(preg_replace(["/^{$this->opt($namePrefixes)}[.\s]+/i", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/u"], ['', '$2 $1'], preg_replace('/[?]/', '', $v['traveller'])))));
                        }
                    } elseif ($seats) {
                        $s->extra()->seats($seats);
                    }

                    $depName = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure:")) . "][1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                        $root);

                    if (empty($depName)) {
                        $depName = $this->http->FindSingleNode("./descendant::text()[" . $this->eq($this->t("Departure:")) . "][1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                            $root);
                    }
                    $s->departure()
                        ->name($depName);

                    // TEL AVIV TLV, ISRAEL -> TLV
                    // PHX PHOENIX, AZ  -> PHX
                    // SAN FRANCISCO, CA -> ???
                    // so check if AirCode or not
                    if (preg_match('/^\s*([A-Z]{3})\s+/', $s->getDepName(), $matches) && !in_array($matches[1], $this->airCodeIsPartCityName)
                        || preg_match('/^.+? ([A-Z]{3}),\s+/', $s->getDepName(), $matches) && !in_array($matches[1], $this->airCodeIsPartCityName)
                    ) {
                        $s->departure()->code($matches[1]);
                    } else {
                        $s->departure()->noCode();
                    }
                    $terminalDep = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]",
                        $root);

                    if (empty($terminalDep)) {
                        $terminalDep = $this->http->FindSingleNode("./descendant::text()[" . $this->eq($this->t("Departure:")) . "][1]/ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'Terminal')][1]",
                            $root);
                    }

                    if (!empty($terminalDep)) {
                        $s->departure()->terminal(preg_replace('/\s*(?:Departure Terminal:|Terminal)\s*/i', '', $terminalDep));
                    }

                    if (!($timeDep = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Departure:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][2]", $root, true, "/{$patterns['time']}/"))) {
                        $timeDep = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Departure:"))}][1]/ancestor::tr[1]/following-sibling::tr[1]", $root, true, "/{$patterns['time']}/");
                    }

                    if (empty($timeDep)) {
                        //Remarks:     DEPART 300P ARRIVE 345P
                        $timeDep = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("DEPART"))}]", $root, true, "#DEPART (\d{3,4}[AP])\b#i");
                        $timeDep = preg_replace("#^(\d{1,2})(\d{2})([AP])$#", '$1:$2 $3M', $timeDep);
                    }

                    if (empty($timeDep)) {
                        //Remarks:
                        //AMERICAN AIRLINES FLIGHT 3 F 11A 218P
                        $timeDep = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("FLIGHT"))}]", $root, true, "#FLIGHT\s*\d+\s*[A-Z]{1}\s*(\d+A?P?)\s*\d#i");
                        $timeDep = preg_replace("#^(\d{1,2})([AP])$#", '$1:00 $2M', $timeDep);
                    }

                    if (!empty($timeDep)) {
                        $s->departure()->date(strtotime($this->normalizeTime($timeDep), $dateDep))
                            ->strict();
                    } else {
                        $s->departure()->date($dateDep)->strict();
                    }

                    $arrName = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                        $root);

                    if (empty($arrName)) {
                        $arrName = $this->http->FindSingleNode("./descendant::text()[" . $this->eq($this->t("Arrival:")) . "][1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                            $root);
                    }
                    $s->arrival()
                        ->name($arrName);

                    // TEL AVIV TLV, ISRAEL -> TLV
                    // PHX PHOENIX, AZ  -> PHX
                    // SAN FRANCISCO, CA -> ???
                    // so check if AirCode or not
                    if (preg_match('/^\s*([A-Z]{3})\s+/', $s->getArrName(), $matches) && !in_array($matches[1], $this->airCodeIsPartCityName)
                        || preg_match('/^.+? ([A-Z]{3}),\s+/', $s->getArrName(), $matches) && !in_array($matches[1], $this->airCodeIsPartCityName)
                    ) {
                        $s->arrival()->code($matches[1]);
                    } else {
                        $s->arrival()->noCode();
                    }

                    $terminalArr = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]",
                        $root);

                    if (empty($terminalArr)) {
                        $terminalArr = $this->http->FindSingleNode("./descendant::text()[" . $this->eq($this->t("Arrival:")) . "][1]/ancestor::tr[1]/following::tr[1]/descendant::text()[contains(normalize-space(), 'Terminal')][1]",
                            $root);
                    }

                    if (preg_match("#(^\s*\+\s*\d+|" . $this->opt($this->t("next day arrival")) . ")#iu",
                        $terminalArr)) {
                        $terminalArr = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][4]",
                            $root);
                    }

                    if (!empty($terminalArr)) {
                        $s->arrival()->terminal(preg_replace('/\s*(?:Arrival Terminal:|Terminal)\s*/i', '', $terminalArr));
                    }

                    $timeArr = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Arrival:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][2]", $root, true, "/{$patterns['time']}/");

                    if (empty($timeArr)) {
                        $timeArr = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Arrival:"))}][1]/ancestor::tr[1]/following-sibling::tr[1]", $root, true, "/{$patterns['time']}/");
                    }

                    if (empty($timeArr)) {
                        //Remarks:     DEPART 300P ARRIVE 345P
                        $timeArr = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("ARRIVE"))}]", $root, true, "#ARRIVE (\d{3,4}[AP])\b#i");
                        $timeArr = preg_replace("#^(\d{1,2})(\d{2})([AP])$#", '$1:$2 $3M', $timeArr);
                    }

                    if (empty($timeArr)) {
                        //Remarks:
                        //AMERICAN AIRLINES FLIGHT 3 F 11A 218P
                        $timeArr = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("FLIGHT"))}]", $root, true, "#FLIGHT\s*\d+\s*[A-Z]{1}\s*\d+A?P?\s*(\d+A?P?)#i");
                        $timeArr = preg_replace("#^(\d{1,2})(\d{2})([AP])$#", '$1:$2 $3M', $timeArr);
                    }

                    if (!empty($timeArr)) {
                        $s->arrival()->date(strtotime($this->normalizeTime($timeArr), $dateArr));
                    } else {
                        $s->arrival()->date($dateArr);
                    }

                    if ((($day = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root, true, "#\s*([+\-]\s*\d+)\s*\w+#iu"))
                        || ($day = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::tr[1]/following-sibling::tr[1]",
                            $root, true, "#\s*([+\-]\s*\d+)\s*\w+#iu")))
                    && $dateDep === $dateArr
                    ) {
                        $s->arrival()
                            ->date(strtotime($day . ' day', $s->getArrDate()));
                    }

                    if (($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]",
                            $root, true, "#" . $this->opt($this->t("next day arrival")) . "#iu")
                        || $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]",
                            $root, true, "#" . $this->opt($this->t("next day arrival")) . "#iu"))
                        && $dateDep === $dateArr
                    ) {
                        $s->arrival()
                            ->date(strtotime('+1 day', $s->getArrDate()));
                    }

                    if (!empty($s->getFlightNumber()) && !empty($s->getArrCode()) && !empty($s->getDepCode()) && empty($s->getDepDate()) && empty($s->getArrDate())) {
                        // it-6868684.eml
                        $s->departure()->noDate();
                        $s->arrival()->noDate();
                    }

                    $aircraft = $this->getField($this->t("Aircraft:"), $root);

                    if (!empty($aircraft)) {
                        $s->extra()->aircraft($aircraft);
                    }

                    $traveledMiles = $this->getField($this->t("Distance (in Miles):"), $root);

                    if (!empty($traveledMiles)) {
                        $s->extra()->miles($traveledMiles);
                    }

                    $cabin = $this->re("#(.*?)(?: /|$)#", $this->getField($this->t("Class:"), $root));

                    if (!empty($cabin)) {
                        $s->extra()->cabin($cabin);
                    }

                    $bookingClass = $this->re("# / ([A-Z])#", $this->getField($this->t("Class:"), $root));

                    if (!empty($bookingClass)) {
                        $s->extra()->bookingCode($bookingClass);
                    }

                    $duration = $this->getField($this->t("Duration:"), $root);

                    if (!empty($duration)) {
                        $s->extra()->duration($duration);
                    }

                    $meal = $this->getField($this->t("Meal:"), $root);

                    if (!empty($meal)) {
                        $s->extra()->meal($meal);
                    }

                    $operator = $this->getField($this->t("Operated by:"), $root, "/:[\/\s]*([^:\/\s].*)$/")
                        ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Operated by:"))}]", $root)
                    ;

                    if (!empty($operator)) {
                        $operator = preg_replace([
                            "/^.*{$this->opt($this->t("Operated by:"))}[:\/\s]*/", // Operado por: /AVIANCA
                            "/(?:{$this->opt($this->t('Confirmed'))}|{$this->opt($this->t('Confirmation#'))}).*$/",
                        ], '', $operator);
                    }

                    if (empty($operator)) {
                        $operator = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'FLIGHT')]", $root, true, "/^[\/\s]*([^\/\s].*\S)\s+FLIGHT/");
                    }

                    if (preg_match("/^(.*?)[-,;\s]+DBA[-:\s]+.+$/", $operator, $m) || preg_match("/^(.*?)[-,;\s]+AS[-:\s]+.+$/", $operator, $m)) {
                        $operator = $m[1];
                        $s->airline()->wetlease();
                    } elseif (preg_match('/^(.*?)[-,;\s]+for/i', $operator, $m)) {
                        $operator = preg_replace('/^(.*\S)\s+-.*$/', '$1', $m[1]);
                    }

                    if (!empty($operator) && mb_strlen($operator) > 50) {
                        $operator = preg_replace([
                            "/^(.+[^\-,;\s])[-,;\s]*\bPTY LTD\b.*$/i", // VIRGIN AUSTRALIA INTERNATIONAL AIRLINES PTY LTD T/A V AUSTRALIA
                            "/^(.+\S)\s*\([^()]*\)$/", // ROYAL JORDANIAN (ALIA - THE ROYAL JORDANIAN AIRLINE)
                        ], [
                            '$1',
                            '$1',
                        ], $operator);
                    }

                    $s->airline()->operator($operator, false, true);

                    // accountNumbers (examples: it-2636804.eml, it-4344772.eml)
                    //$this->logger->debug();
                    if (($airlineName && $this->providerCode
                        && ($this->getProviderCodeByIata($airlineName) === $this->providerCode || $airlineName === 'AA'))
                    ) {
                        /* Warning! There are numbers from another provider */
                        foreach ($pax as $i => $p) {
                            $num = $i + 1;
                            $ffNumber = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Frequent flight number:"))}][{$num}]",
                                $root, true, '/' . $this->opt($this->t("Frequent flight number:")) . '\s*(.+)/'); //\d+

                            if ($ffNumber === null) {
                                $ffNumber = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Frequent flight number:"))}][{$num}]/following::text()[normalize-space()][1]",
                                    $root);
                            }

                            if ($ffNumber === null) {
                                $ffNumber = $this->http->FindSingleNode($xpathHeader . "/descendant::text()[{$this->eq($this->t("Frequent flight number:"))}]/following::text()[normalize-space()][1]",
                                    $root);
                            }

                            if (preg_match("/^[A-Z\d]{7,}$/", $ffNumber)) {
                                // 40105912683
                                $itAccountNumbers[] = $ffNumber;

                                continue;
                            } elseif ($airlineNameFull
                                && (preg_match("/^([A-Z\d]{7,})\s+(?i){$this->opt($airlineNameFull)}$/", $ffNumber, $m)
                                    || preg_match("/^{$this->opt($airlineNameFull)}\s+(?-i)([A-Z\d]{7,})$/i", $ffNumber, $m)
                                    || preg_match("/^([A-Z\d]{7,})\s+\D*$/i", $ffNumber, $m)
                                )
                            ) {
                                // 40105912683 ETHIOPIAN AIRLINES    |    ETHIOPIAN AIRLINES 40105912683
                                $itAccountNumbers[] = $m[1];
                            }
                        }
                    }

                    if ($this->http->XPath->query("descendant::tr[ *[normalize-space()][1][{$this->eq($this->t("Total:"))}] and *[normalize-space()][3] ]", $resNode)->length === 1) {
                        if ($cur = $this->http->FindSingleNode($xpathFooter . "/descendant::td[{$this->eq($this->t("Total:"))}]/following-sibling::td[normalize-space()][1]", $root, true, '/^[A-Z]{3}$/')) {
                            $f->price()->currency($cur);
                        }
    
                        if ($tot = $this->http->FindSingleNode($xpathFooter . "/descendant::td[{$this->eq($this->t("Total:"))}]/following-sibling::td[normalize-space()][2]", $root, true, '/^\d[,.\'\d]*$/')) {
                            $f->price()->total(PriceHelper::parse($tot, $cur));
                        }
                    }

                    if (0 < $this->http->XPath->query("descendant::node()[normalize-space(.)='Ship:']",
                            $root)->length && false === stripos($root->nodeValue, 'Flight Number')) {
                        $isInvalidCruise[$rl] = true;
                    }
                }

                // travellers
                $itPassengers = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', str_replace(['​', '?'], '', $itPassengers));
                $itPassengers = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', str_replace(['​', '?'], '', $itPassengers));
                $itPassengers = array_unique(array_filter($itPassengers));

                if (count($itPassengers)) {
                    //$itPassengers = array_map('ucwords', array_map('strtolower', preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $itPassengers)));
                    $f->general()->travellers($itPassengers);
                } else {
                    //$pax = array_map('ucwords', array_map('strtolower', preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $pax)));
                    $f->general()->travellers($pax);
                }

                // Ticket Numbers
                $ticketsText = $this->htmlToText($this->http->FindHTMLByXpath("//node()[{$this->starts($this->t("Your ticket(s) is/are:"))}]/ancestor::tr[count(descendant::text()[normalize-space()])>1][1]"));

                if (preg_match_all("/^[ ]*({$patterns['travellerName']})[ ]*:[ ]*({$patterns['eTicket']})[ ]*$/mu", $ticketsText, $ticketMatches)) {
                    $itPassengers = array_merge($itPassengers, $ticketMatches[1]);
                    $itPassengers = array_map('ucwords', array_map('strtolower', preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $itPassengers)));

                    if (empty($f->getTravellers())) {
                        $f->general()
                            ->travellers($itPassengers);
                    }

                    foreach ($ticketMatches[2] as $ticketM) {
                        $itTicketNumbers = array_merge($itTicketNumbers, preg_split('/\s*[,]+\s*/', $ticketM));
                    }
                }

                if (count($itTicketNumbers) === 0) {
                    $itTicketNumbers = $this->http->FindNodes("//node()[{$this->starts($this->t("Your ticket(s) is/are:"))}]/following-sibling::a[normalize-space()]");
                }

                if (count($itTicketNumbers) === 0) {
                    $itTicketNumbers = $this->http->FindNodes("//node()[{$this->contains($this->t("Your ticket(s) is/are:"))}]/following-sibling::a[normalize-space()]");
                }

                $itTicketNumbers = array_filter($itTicketNumbers);

                if (count($itTicketNumbers) > 0 && !empty($this->http->FindSingleNode("(//node()[{$this->contains($this->t("Your ticket(s) is/are:"))}])[1]"))) {
                    $itTicketNumbersByTravellers = [];

                    foreach ($itTicketNumbers as $ticket) {
                        $itTicketNumbersByTravellers[$ticket] = null;
                        $nameText = implode("\n", $this->http->FindNodes("//text()[{$this->contains($ticket)}]/ancestor::*[contains(normalize-space(), ':')][1]//text()[normalize-space()]"));

                        if (preg_match("/(?:^|\n) *([[:alpha:]][[:alpha:]\- ]+?)\s*:\s*(?:\d{8}[\/\d]*(?:\s*\(\*\))?\s*,\s*)*{$this->opt($ticket)}\s*(?:,|\n|$)/", $nameText, $m)) {
                            $itTicketNumbersByTravellers[$ticket] = $m[1];
                        }
                    }
                }

                if (count($itTicketNumbers) === 0) { // experimental (examples: it-105916175.eml, it-1556639.eml, it-1556844.eml, it-1556849.eml, it-1843767.eml)
                    $ticketInfoContainers = $this->http->XPath->query("//*[ tr[normalize-space()][1][{$this->eq($this->t("Ticket Information"))}] ]");

                    if ($ticketInfoContainers->length === 1) {
                        $itTicketNumbers = array_filter($this->http->FindNodes("descendant::tr/*[{$this->eq($this->t("Ticket Number:"))}]/following-sibling::*[normalize-space()][1]", $ticketInfoContainers->item(0), "/^(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+)?({$patterns['eTicket']})$/"));
                    }
                }

                if (empty($itTicketNumbers) && !empty($this->text)) {
                    $itTicketNumbersByTravellers = [];

                    if (preg_match_all("/.{40,}[ ]{2,}{$this->opt('eTicket Receipt(s):')}\n(.+[ ]{2,}\d{8,}\n[\s\S]+?\n\n)/", $this->text, $ticketMatches)) {
                        $ticketText = implode("\n", $ticketMatches[1]);

                        if (preg_match_all("/^(.+)[ ]{2,}(\d{8,})$/m", $ticketText, $ticketMatches)) {
                            $itTicketNumbers = array_unique($ticketMatches[2]);

                            foreach ($ticketMatches[0] as $i => $v) {
                                $itTicketNumbersByTravellers[$ticketMatches[2][$i]] = null;

                                if (preg_match("/^ {0,5}\W ([A-Z][A-Z \-]+? *\/ *[A-Z \-]+?) {2}/mu", $v, $m2)) {
                                    $itTicketNumbersByTravellers[$ticketMatches[2][$i]] = $m2[1];
                                }
                            }
                            $itTicketNumbers = array_unique($itTicketNumbers);
                        }
                    }
                }

                if (empty($itTicketNumbers) && !empty($this->text)) {
                    $itTicketNumbersByTravellers = [];

                    if (preg_match_all("/^[ ]{0,10}{$this->opt('TICKET NUMBER')}[ ]{5,}(\d{8,})$/", $this->text, $ticketMatches)) {
                        $itTicketNumbers = array_unique($ticketMatches[1]);
                    }
                }

                if (isset($itTicketNumbersByTravellers) && count($itTicketNumbersByTravellers) === 0) {
                    foreach ($itTicketNumbers as $ticket) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::table[2]/descendant::text()[{$this->eq($this->t('Passenger:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Passenger:'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/");
                        $pax = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', str_replace(['​', '?'], '', $pax));
                        $pax = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', str_replace(['​', '?'], '', $pax));

                        if (!empty($pax)) {
                            $itTicketNumbersByTravellers[$ticket] = $pax;
                        }
                    }
                }

                if (!empty($itTicketNumbersByTravellers)) {
                    foreach ($itTicketNumbersByTravellers as $num => $name) {
                        $name = ucwords(strtolower(preg_replace(["/^{$this->opt($namePrefixes)}[.\s]+/i", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/u"], ['', '$2 $1'], str_replace('?', '', $name))));
                        $f->issued()->ticket($num, false, $name);
                    }
                } elseif (count($itTicketNumbers)) {
                    $f->issued()->tickets(array_unique($itTicketNumbers), false);
                }

                // accountNumbers
                if (count($itAccountNumbers)) {
                    $accounts = array_unique($itAccountNumbers);

                    foreach ($accounts as $account) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::table[2]/descendant::text()[{$this->eq($this->t('Passenger:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Passenger:'))}\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])/");
                        $pax = preg_replace("/^{$this->opt($namePrefixes)}[.\s]+/i", '', str_replace(['​', '?'], '', $pax));
                        $pax = preg_replace("/\s+{$this->opt($namePrefixes)}$/i", '', str_replace(['​', '?'], '', $pax));

                        if (!empty($pax)) {
                            $f->program()
                                ->account($account, false, $pax);
                        } else {
                            $f->program()
                                ->account($account, false);
                        }
                    }
                }

                // price
                if (empty($f->getPrice())) {
                    if ($cur = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t("Total:"))}] and *[normalize-space()][3] ]/*[normalize-space()][2]", null, true, '/^[A-Z]{3}$/')) {
                        $f->price()->currency($cur);
                    }

                    if ($tot = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t("Total:"))}] and *[normalize-space()][3] ]/*[normalize-space()][3]", null, true, '/^\d[,.\'\d]*$/')) {
                        $f->price()->total(PriceHelper::parse($tot, $cur));
                    }
                }

                // Cost
                $xpathFare = "//*/tr[ *[normalize-space()][2] ][1][ *[normalize-space()][1][{$this->eq($this->t("Fare"), "translate(.,':','')")}] and following-sibling::tr/*[normalize-space()][1][{$this->eq($this->t("Total:"))}] ]";
                $baseFare = $this->http->FindSingleNode($xpathFare . "/*[normalize-space()][3]", null, true, '/^\d[,.\'\d]*$/');
                $currencyFare = $this->http->FindSingleNode($xpathFare . "/*[normalize-space()][2]");

                if ($baseFare !== null && $currencyFare
                    && $f->getPrice() && $f->getPrice()->getCurrencyCode() !== $currencyFare
                ) {
                    $xpathFare = "//*/tr[ *[normalize-space()][2] ][2][ *[normalize-space()][1][{$this->eq($this->t("Equivalent amount paid"), "translate(.,':','')")}] and following-sibling::tr/*[normalize-space()][1][{$this->eq($this->t("Total:"))}] ]";
                    $baseFare = $this->http->FindSingleNode($xpathFare . "/*[normalize-space()][3]", null, true, '/^\d[,.\'\d]*$/');
                    $currencyFare = $this->http->FindSingleNode($xpathFare . "/*[normalize-space()][2]");
                }

                if ($baseFare !== null && $currencyFare
                    && $f->getPrice() && $f->getPrice()->getCurrencyCode() === $currencyFare
                ) {
                    $f->price()->cost(PriceHelper::parse($baseFare, $currencyFare));
                }

                // Taxes and Fees
                $fees = $this->http->XPath->query("//text()[{$this->contains(array_unique(array_merge((array) $this->t("taxDetect"), [" Tax", " Fee"])))}]/ancestor::td[1] | //tr[ *[1][{$this->starts($this->t("feeRowStart"))}] ]/*[5]");

                foreach ($fees as $fee) {
                    $feeCharge = $this->http->FindSingleNode("preceding-sibling::td[2]", $fee, true, '/^\d[,.\'\d]*$/');
                    $currencyFee = $this->http->FindSingleNode("preceding-sibling::td[3]", $fee);

                    if ($feeCharge !== null && $currencyFee
                        && $f->getPrice() && $f->getPrice()->getCurrencyCode() === $currencyFee
                    ) {
                        $f->price()->fee($fee->nodeValue, PriceHelper::parse($feeCharge, $currencyFee));
                    }
                }

                $this->uniqueFlightSegments($f);

                foreach ($email->getItineraries() as $i => $itinerary) {
                    /** @var \AwardWallet\Schema\Parser\Common\Itinerary $itinerary */
                    foreach ($itinerary->getConfirmationNumbers() as $confirmationNumber) {
                        if (isset($isInvalidCruise[(int) $confirmationNumber[0]]) && $isInvalidCruise[(int) $confirmationNumber[0]]) {
                            $email->removeItinerary($itinerary);
                        }
                    }
                }
            }

            if (count($email->getItineraries()) == 1) {
                /** @var Flight $flight */
                $flight = array_values($email->getItineraries())[0];
                $tot = $this->http->FindSingleNode("//text()[{$this->contains($this->t('PRIX BILLET AVION'))}]", null,
                    false, "#{$this->opt($this->t('PRIX BILLET AVION'))}\s*(.+)#");

                if (preg_match("#^\s*(?<total>\d[\d,. ]*)\s*(?<cur>[A-Z]{3})\s*#", $tot, $m)
                    || preg_match("#^\s*(?<cur>[A-Z]{3})\s*(?<total>\d[\d,. ]*)\s*#", $tot, $m)) {
                    $flight->price()
                        ->total($this->amount(trim($m['total'])))
                        ->currency($m['cur']);
                }
            }

            //#################
            //##   HOTELS   ###
            //#################
            $xpathFragmentHotel = "//text()[{$this->eq($this->t("CHECKIN"))}]/ancestor::tr[{$this->contains($this->t("Room Details:"))} or {$this->contains($this->t("ROOMS"))} or {$this->contains($this->t("Guest(s):"))} or {$this->contains($this->t("RATE"))} or {$this->contains($this->t("Termos:"))}][1]";
            $hotels = $this->http->XPath->query($xpathFragmentHotel);

            if ($hotels->length == 0 && count($this->http->FindNodes("//img[contains(@src, '/icon-hotel.')]")) > 0) {
                $this->logger->debug("Found hotels but not selected");

                return;
            }

            foreach ($hotels as $root) {
                $h = $email->add()->hotel();

                if (preg_match("#\b(" . $this->opt($this->t("Confirmed")) . ")\b#ui", $root->nodeValue, $m)) {
                    $h->setStatus($m[1]);
                }
                $confirmationNumber = $this->nextText($this->t("Confirmation Number:"), $root)
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Confirmation#"))}]", $root, true, "/#\s*([A-z\d][- A-z\d]+[A-z\d])[-\s\,]*$/")
                ;

                if (preg_match("/^(OK|PURE|FAM TRIP)$/i", $confirmationNumber)) {
                    $h->general()->noConfirmation();
                } elseif (!empty($confirmationNumber)) {
                    $h->general()->confirmation($confirmationNumber);
                } else {
                    if (is_array($this->t("Confirmation#"))) {
                        $confTitle = array_map(function ($v) {
                            return trim($v, '#');
                        }, $this->t("Confirmation#"));
                    } else {
                        $confTitle = trim($this->t("Confirmation#"), '#');
                    }

                    if (empty($this->http->FindSingleNode(".//text()[" . $this->contains($confTitle) . "]", $root))) {
                        $h->general()->noConfirmation();
                    }
                }

                if (count($travellers) == 0) {
                    $traveller = $this->re("/eInvoice\,\s*\w+\s*\d+\s*for\s*([A-Z\s]{5,})/", $this->subject);
                }

                if (count($travellers) > 0) {
                    $h->general()
                        ->travellers($travellers);
                } elseif (!empty($traveller)) {
                    $h->general()
                        ->traveller($traveller);
                }

                $account = $this->nextText($this->t("Member ID:"), $root);

                if (!empty($account)) {
                    $h->program()
                        ->account($account, preg_match("/^(X{4,}|\*{4,})/i", $account) !== 0);
                }

                if (empty($account)) {
                    $accounts = array_unique(array_filter($this->http->FindNodes("./following::text()[normalize-space()='Corporate Discount #:'][1]/following::text()[normalize-space()][1]", $root, "/^(\d+)$/")));

                    if (count($accounts) > 0) {
                        $h->program()
                            ->accounts($accounts, false);
                    }
                }

                $h->hotel()
                    ->name($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root));

                if ($this->http->XPath->query("descendant::text()[{$this->eq($this->t("Room Details:"))}]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][1][not({$this->contains($this->t("CHECKIN"))})]  |  descendant::text()[{$this->eq($this->t("Address:"))}]", $root)->length === 0
                ) {
                    $h->hotel()->noAddress();
                } else {
                    $h->hotel()->address(
                        $this->re("/^(.{3,}?)\s*(?:Phone:|Ph:|$)/", implode(" ", $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Room Details:"))}]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root)))
                        ?? $this->nextText($this->t("Address:"), $root)
                    );
                }

                $h->booked()
                    ->checkIn($this->normalizeDate($this->nextText($this->t("CHECKIN"), $root)))
                    ->checkOut($this->normalizeDate($this->nextText($this->t("CHECKOUT"), $root)));

                $contactsText = implode("\n", $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Room Details:"))}]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

                $phone = $fax = null;

                if (preg_match("/{$this->opt($this->t("PHONE"))}[:\s]*({$patterns['phone']})[-\s]*$/m", $contactsText, $m)) {
                    $phone = $m[1];
                } else {
                    $phone = $this->nextText('Phone:', $root, 1, "/^{$patterns['phone']}$/");
                }

                if (preg_match("/{$this->opt($this->t("FAX"))}[:\s]*({$patterns['phone']})[-\s]*$/m", $contactsText, $m)) {
                    $fax = $m[1];
                } else {
                    $fax = $this->nextText('Fax:', $root, 1, "/^{$patterns['phone']}$/");
                }

                $h->hotel()->phone($phone, false, true)->fax($fax, false, true);

                $h->booked()
                    ->guests($this->nextText($this->t("Guest(s):"), $root, 1, "/^\d{1,3}(?:\s*\(|$)/"), false, true)
                    ->rooms($this->nextText($this->t("ROOMS"), $root));

                $room = $h->addRoom();
                $rate = $this->nextText($this->t("RATE"), $root, 1, "/^(.*\d.*?)[*\s]*$/");

                if ($rate && strlen($rate) < 400) {
                    $room->setRate($rate);
                }

                if ($this->http->XPath->query("./following::text()[contains(normalize-space(), 'RATES AND EFFECTIVE DATES')][1]", $root)->length > 0) {
                    $rate = implode('; ', $this->http->FindNodes("//text()[{$this->eq($h->getHotelName())}]/following::text()[contains(normalize-space(), 'RATES AND EFFECTIVE DATES')][1]/ancestor::tr[1]/descendant::tr/descendant::text()[contains(normalize-space(), 'EFFECTIVE')]/ancestor::tr[1]"));

                    if (!empty($rate) && strlen($rate) < 400) {
                        $room->setRate($rate);
                    }
                }

                if ($this->http->XPath->query("descendant::text()[{$this->eq($this->t("Room Details:"))}]", $root)->length > 0) {
                    $roomDescription = implode('. ', $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Room Details:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

                    if ($roomDescription === '') {
                        $roomDescription = $this->http->FindSingleNode("descendant::tr[descendant::text()[normalize-space()][1][{$this->eq($this->t("Room Details:"))}] and count(*[normalize-space()])=1]", $root, true, "/^{$this->opt($this->t("Room Details:"))}[:\s]*(.{4,})$/");
                    }

                    $room->setDescription($roomDescription);
                }

                $tot = $this->nextText($this->t("Approximate Total Price"), $root);

                if (preg_match("#^\s*(?<total>\d[\d,. ]*)\s*(?<cur>[A-Z]{3})\s*$#", $tot, $m)
                    || preg_match("#^\s*(?<cur>[A-Z]{3})\s*(?<total>\d[\d,. ]*)\s*$#", $tot, $m)) {
                    $h->price()
                        ->total($this->amount(trim($m['total'])))
                        ->currency($m['cur']);
                }

                $cancel = array_values(array_filter(
                    $this->http->FindNodes(
                        ".//text()[{$this->eq($this->t("Termos:"))}]/following::text()[normalize-space(.)!=''][1]",
                        $root,
                        "#.*Cancel.*#iu")
                ));

                if (count($cancel) == 1 && !empty($cancel[0])) {
                    $h->general()
                        ->cancellation($cancel[0]);
                }
                $this->detectDeadLine($h);
            }

            //##############
            //##   CAR   ### (examples: it-34291693.eml, it-468644293.eml)
            //##############
            $xpathFragmentCar = "//text()[{$this->eq($this->t("Pick Up:"))} or {$this->eq($this->t("Pick Up At:"))}]/ancestor::tr[ ancestor::tr[1]/descendant::img[not(contains(@src,'calendar-plus'))] ][1]/..";
//            $this->logger->debug('$xpathFragmentCar = ' . $xpathFragmentCar);
            $cars = $this->http->XPath->query($xpathFragmentCar);

            if ($cars->length === 0 && $this->http->XPath->query("//img[contains(@src,'/icon-car.') or contains(@src,'/car-segment.')]")->length > 0) {
                $this->logger->debug("Found cars but not selected");
                $email = null;

                return;
            }

            foreach ($cars as $root) {
                $r = $email->add()->rental();

                $statusTexts = array_filter($this->http->FindNodes("descendant::text()[{$this->contains($this->t("Confirmation#"))} or starts-with(normalize-space(),'Confirmation:')]/preceding::text()[string-length(normalize-space())>2][position()<5]", $root, "/^({$this->opt($this->t('Confirmed'))})$/iu"));

                if (count(array_unique($statusTexts)) === 1) {
                    $status = array_shift($statusTexts);
                    $r->general()->status($status);
                }

                $dateText = $this->http->FindSingleNode("tr[1]/td[2]/descendant::text()[{$this->eq($this->t("Pick up:"))}]/ancestor::td[1]/following-sibling::*[normalize-space()][1]", $root)
                    ?? $this->http->FindSingleNode('tr[1]/td[2]', $root)
                ;
                $date = $this->normalizeDate($dateText);

                $number = $this->nextText($this->t("Confirmation Number:"), $root)
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Confirmation#"))} or starts-with(normalize-space(),'Confirmation:')]", $root, true, "/#\s*(\w[- \w]+\w)[-\s]*$/")
                ;

                if (!empty($number)) {
                    $r->general()->confirmation($number);
                } else {
                    $r->general()
                        ->noConfirmation();
                }

                $r->general()
                    ->travellers($travellers);

                $account = $this->nextText($this->t("Member ID:"), $root, 1, "/^\s*[^A-z\d]{0,2}(.{5,})$/iu");

                if (!empty($account)) {
                    $r->program()
                        ->account(preg_replace("#\xE2\x80\x8B#", '', $account),
                            preg_match("/^(X{4,}|\*{4,})/i", $account) !== 0);
                }

                $node = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pick Up:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                if (preg_match("#^(.*?) \w{2}\s*(\d+)$#", $node, $m)) {
                    $r->pickup()->date($this->normalizeDate($m[1], $date));

                    if ($flightArrName = $this->getFlightArrName($m[2], $email)) {
                        $r->pickup()->location($flightArrName);
                    }
                    $r->dropoff()->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), $date));

                    $dropOffLocation = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off At:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

                    if (!empty($dropOffLocation)) {
                        $r->dropoff()->location($dropOffLocation);
                    }
                } elseif (preg_match("#^(\d+:.*?[ap]m)$#i", $node, $m)) {
                    $r->pickup()
                        ->date($this->normalizeDate($m[1], $date))
                        ->noLocation();
                    $r->dropoff()
                        ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root), $date))
                        ->noLocation();
                } else {
                    $patterns['date'] = '(?:'
                        // Wednesday, March 20, 2019
                        // Wednesday, 20 March, 2019
                        // March 20, 2019
                        // 20 March, 2019
                        // Wednesday, March 20
                        // Terça-feira, 20 March
                        . '(?:[[:alpha:]-]{2,}[ ]*,[ ]*)?(?:[[:alpha:]]{3,}[ ]*\d{1,2}\b|\b\d{1,2}[ ]*[[:alpha:]]{3,})(?:[ ]*,[ ]*\d{4})?'
                        . ')';

                    // GOLD COAST, AUSTRALIA (OOL) Wednesday, March 20, 2019 at 7:00PM
                    // GOLD COAST, AUSTRALIA (OOL) Wednesday, 20 March 7:00PM
                    // BRISBANE, AUSTRALIA (BNE) March 16, 2019 at 6:00AM
                    $patterns['locDateTime'] = "/^(?<loc>(?!(?:on|at|alle) ).{3,}?)[ ]+(?<date>{$patterns['date']})(?:\s* (?:at|alle) )?\s*(?<time>{$patterns['time']})(?:[ ]*\n|\s*$)/u";

                    // BURLINGTON VT, VT (BTV) at 06:50 Wednesday, 12 March
                    // BURLINGTON VT, VT (BTV) at 18:41
                    $patterns['locTimeDate'] = "/^(?<loc>(?!(?:on|at|alle) ).{3,}?)(?:\s* (?:at|alle) )?\s*(?<time>{$patterns['time']})(?:[ ]+(?<date>{$patterns['date']}))?(?:[ ]*\n|\s*$)/u";

                    $pickupHtml = $this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t("Pick Up At:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1][not({$this->starts($this->t("Drop Off:"))} or {$this->starts($this->t("Drop Off At:"))})]", null, $root);
                    $pickupText = $this->htmlToText($pickupHtml);

                    if (preg_match($patterns['locDateTime'], $pickupText, $m)
                        || preg_match($patterns['locTimeDate'], $pickupText, $m)
                    ) {
                        $r->pickup()->location($m['loc']);

                        if (empty($m['date'])) {
                            $r->pickup()->date($this->normalizeDate($m['time'], $date));
                        } else {
                            $r->pickup()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                        }
                    } elseif (preg_match("/^(?:at|alle)\s+(?<time>{$patterns['time']})\s+(?<loc>.{3,})$/s", $pickupText, $m)) {
                        $r->pickup()
                            ->location(preg_replace("#\s+#", ' ', $m['loc']))
                            ->date(strtotime($m['time'], $date));
                    } elseif (preg_match("/^([^:\n]{3,})(?:\n|$)/", $pickupText, $m)) {
                        $r->pickup()
                            ->location($m[1])
                            ->date($date);
                    }

                    $dropoffHtml = $this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t("Drop Off At:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                        null, $root);
                    $dropoffText = $this->htmlToText($dropoffHtml);

                    if (preg_match($patterns['locDateTime'], $dropoffText, $m)
                        || preg_match($patterns['locTimeDate'], $dropoffText, $m)
                    ) {
                        $r->dropoff()->location($m['loc']);

                        if (empty($m['date'])) {
                            $r->dropoff()->date($this->normalizeDate($m['time'], $date));
                        } else {
                            $r->dropoff()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                        }
                    } elseif (preg_match("/^([^:\n]{3,})\s+([-[:alpha:]]+,\s*\d{1,2}\s*[[:alpha:]]+)(?:\n|$)/u", $dropoffText, $m)
                        || preg_match("/^([^:\n]{3,})(?:\n|$)/", $dropoffText, $m)
                    ) {
                        $r->dropoff()
                            ->location($m[1]);

                        if (isset($m[2]) && !empty($m[2])) {
                            $r->dropoff()
                                ->date(strtotime($m[2]));
                        } else {
                            $r->dropoff()
                                ->date($date);
                        }
                    } elseif (preg_match("/^(?:on\s+(?<date>{$patterns['date']})\s+)?(?:at|alle)\s+(?<time>{$patterns['time']})\s+(?<loc>.{3,})$/s", $dropoffText, $m)) {
                        if (empty($m['date'])) {
                            $r->dropoff()->date(strtotime($m['time'], $date));
                        } else {
                            $r->dropoff()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                        }

                        $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['loc']));
                    }
                }

                // it-468644293.eml
                $xpathPoints = "descendant::tr[ *[normalize-space()][1][{$this->starts($this->t("Pick Up:"))} or {$this->starts($this->t("Pick Up At:"))}] and *[normalize-space()][2][{$this->starts($this->t("Drop Off:"))} or {$this->starts($this->t("Drop Off At:"))}] ]";

                if (empty($r->getPickUpDateTime()) && empty($r->getDropOffDateTime())) {
                    $datesVal = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'⋅') and contains(normalize-space(),'Day') and contains(normalize-space(),'Hour')]/preceding::text()[normalize-space()][1]", $root);
                    $timePickUp = $this->http->FindSingleNode($xpathPoints . "/following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=2]/*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
                    $timeDropOff = $this->http->FindSingleNode($xpathPoints . "/following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=2]/*[normalize-space()][2]", $root, true, "/^{$patterns['time']}/");

                    if ($datesVal && count($dates = preg_split('/\s+[-–]\s+/', $datesVal)) === 2) {
                        $datePickUp = $this->normalizeDate($dates[0]);
                        $dateDropOff = $this->normalizeDate($dates[1]);

                        if ($datePickUp && $timePickUp) {
                            $r->pickup()->date(strtotime($timePickUp, $datePickUp));
                        }

                        if ($dateDropOff && $timeDropOff) {
                            $r->dropoff()->date(strtotime($timeDropOff, $dateDropOff));
                        }
                    }
                }

                if (empty($r->getPickUpLocation()) && empty($r->getDropOffLocation())) {
                    $r->pickup()->location($this->http->FindSingleNode($xpathPoints . "/*[normalize-space()][1]", $root, true, "/^(?:{$this->opt($this->t("Pick Up:"))}|{$this->opt($this->t("Pick Up At:"))})[:\s]*(.{3,})$/"));
                    $r->dropoff()->location($this->http->FindSingleNode($xpathPoints . "/*[normalize-space()][2]", $root, true, "/^(?:{$this->opt($this->t("Drop Off:"))}|{$this->opt($this->t("Drop Off At:"))})[:\s]*(.{3,})$/"));
                }

                $phone = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pick Up At:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[" . $this->starts($this->t("Telephone:")) . "]",
                    $root, true, "#{$this->opt($this->t("Telephone:"))}\s*(.+)#");

                if (empty($phone)) {
                    $phone = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Telephone:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#^([\d\-\(\)\+ ]+)$#");
                }
                $r->pickup()->phone($phone, false, true);

                $phone = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off At:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[" . $this->starts($this->t("Telephone:")) . "]",
                    $root, true, "#{$this->opt($this->t("Telephone:"))}\s*(.+)#");

                if (empty($phone)) {
                    $phone = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Telephone:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root, true, "#^([\d\-\(\)\+ ]+)$#");
                }
                $r->dropoff()->phone($phone, false, true);

                $r->car()->type($this->nextText($this->t("Car Type:"), $root));

                $company = ($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root, true, "/^\s*(?:[[:alpha:]][ [:alpha:]]+[[:alpha:]]\s*[:]+\s*)?(.{2,})$/u"));

                if (($code = $this->normalizeRentalProvider($company))) {
                    $r->program()->code($code);
                } else {
                    $r->extra()->company($company);
                }

                if ($tot = $this->nextText($this->t("Approximate Total Price"), $root)) {
                    $cur = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Approximate Total Price")) . "]/ancestor::tr[1]/preceding-sibling::tr[./td[2]][last()]/td[2]",
                        $root, true, "#^\s*([A-Z]{3})\s*$#");

                    if (empty($cur)) {
                        $nextpos = 2 + count($this->http->FindNodes(".//td[" . $this->eq($this->t("Approximate Total Price")) . "]/preceding-sibling::td",
                                $root));

                        if (!empty($this->http->FindSingleNode(".//tr/td[" . ($nextpos - 1) . "][" . $this->eq($this->t("Approximate Total Price")) . "]",
                            $root))) {
                            $cur = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Approximate Total Price")) . "]/ancestor::tr[1]/preceding-sibling::tr[./td[" . $nextpos . "][not(" . $this->contains([
                                '0',
                                '1',
                                '2',
                                '3',
                                '4',
                                '5',
                                '6',
                                '7',
                                '8',
                                '9',
                            ]) . ")]][1]/td[" . $nextpos . "]", $root, true, "#^\s*([A-Z]{3})\s*$#");
                        }
                    }
                    $r->price()
                        ->total($tot)
                        ->currency($cur);
                }
                //it-105916175
                if (!empty($r->getPickUpLocation()) && empty($r->getDropOffLocation()) && (($r->getPickUpDateTime() - $r->getDropOffDateTime()) > 0)) {
                    $email->removeItinerary($r);
                }
            }

            //################
            //##   EVENT   ###
            //################
            // examples: it-14474310
            $xpathFragmentEvents = '//img[contains(@src,"/icon-event.")]/ancestor::td[ ./following-sibling::td[normalize-space(.)] ][1]/following-sibling::td[normalize-space(.)][1]/descendant::tr[not(.//tr) and normalize-space(.)][1]/..';
            $events = $this->http->XPath->query($xpathFragmentEvents);

            if ($events->length === 0 && count($this->http->FindNodes('//img[contains(@src,"/icon-event.")]')) > 0) {
                $this->logger->debug('Found events but not selected');
                $email = null;

                return;
            }

            foreach ($events as $root) {
                $r = $email->add()->event();
                $r->setEventType(EVENT_EVENT);

                $status = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][1]', $root, true,
                    "/^({$this->opt($this->t('Confirmed'))})$/iu");

                if ($status) {
                    $r->general()->status($status);
                }

                $r->general()
                    ->travellers($travellers);

                $dateText = $this->http->FindSingleNode('./tr[1]/td[2]/descendant::text()[normalize-space(.)][1]',
                    $root);
                $dateNormal = $this->normalizeDate($dateText);

                if ($dateNormal) {
                    $r->booked()
                        ->start($dateNormal)
                        ->noEnd();
                }

                $nameText1 = $this->nextText($this->t('Departure:'), $root);
                $nameText2 = $this->nextText($this->t('Tour:'), $root);

                if ($nameText1 && $nameText2) {
                    $r->place()->name("Departure $nameText1 (Tour $nameText2)");
                }

                $conf = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Tour Code:'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, '/^([A-Z\d]{5,})$/');

                if (!$conf) {
                    $conf = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Confirmation#")) . "]",
                        $root, true, "#\#\s*(\w+)#");
                }

                if ($conf) {
                    $r->general()->confirmation($conf);
                }

                $remarks = $this->nextText($this->t('Termos:'), $root);

                if ($remarks) {
                    $r->place()->address($remarks);
                }

                if (preg_match('/\bTRAILS\b/i', $r->getName()) > 0) {
                    // Tour: TRAILS OF INDOCHINA
                    $email->removeItinerary($r);
                } elseif ($this->nextText($this->t('Room Type:'), $root, 1, '/[_A-z\d]+\(\)/') !== null) {
                    // Room Type: getTourAccommodation()
                    $email->removeItinerary($r);
                } elseif (empty($remarks) && $conf) {
                    // `Remarks:` is missing
                    $email->removeItinerary($r);
                }
            }

            //#################
            //##    RAILS   ###
            //#################
            if ($date = $this->normalizeDate($this->nextText($this->t("Date issued:")))) {
                $this->date = $date;
            }

            $xpathRail = "//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::tr[1]/following-sibling::tr[(" . $this->contains($this->t("Arrival:")) . ") and string-length(.) > 13]/ancestor-or-self::tr[./ancestor::*[1][" . $this->contains($this->t("Class:")) . " or " . $this->contains($this->t("Seat:")) . "] or contains(., 'Departure')][1]/ancestor::*[1][contains(preceding::img[1]/@src, 'icon-rail')]";
            // $this->logger->debug('$xpathRail = ' . $xpathRail);
            $nodes = $this->http->XPath->query($xpathRail);

            if ($nodes->length > 0) {
                $rail = [];

                foreach ($nodes as $root) {
                    if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Confirmation#")) . " or " . $this->starts($this->t("Airline Confirmation:")) . "]",
                        $root, true, "#[\#:]\s*(\w+)$#")) {
                        $rail[$rl][] = $root;
                    } elseif ($rl = $this->re("#^(\w+)$#", $this->nextText($this->t("Reservation code:")))) {
                        $rail[$rl][] = $root;
                    } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation code:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                        $root, true, "#^(\w+)$#")) {
                        $rail[$rl][] = $root;
                    } elseif ($rl = $this->re("#^(\w+)$#", $this->nextText($this->t("Reservation code:")))) {
                        $rail[$rl][] = $root;
                    } else {
                        $this->logger->debug("rl not matched");

                        return;
                    }
                }

                foreach ($rail as $rl => $roots) {
                    $f = $email->add()->train();

                    $f->general()
                        ->confirmation($rl)
                        ->travellers($travellers);

                    foreach ($roots as $root) {
                        $s = $f->addSegment();

                        $date = $this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)!=''][1]",
                            $root));

                        $name = $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''][1])[1]",
                            $root,
                            true);

                        // status
                        if (preg_match("/\b({$this->opt($this->t("Confirmed"))})\b/iu",
                            $this->http->FindHTMLByXpath('.', null, $root), $m)) {
                            $s->setStatus($m[1]);
                        }

                        $s->extra()
                            ->service($name)
                            ->noNumber();

                        $s->departure()
                            ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                                $root));

                        if (!($depTime = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root, true, "#\d+:\d+(?:[ap]m)?#i"))) {
                            $depTime = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::tr[1]/following-sibling::tr[1]",
                                $root, true, "#\d+:\d+(?:[ap]m)?#i");
                        }
                        $s->departure()->date(strtotime($depTime, $date));

                        $s->arrival()
                            ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]",
                                $root));

                        if (!($arrTime = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root, true, "#\d+:\d+(?:[ap]m)?#i"))) {
                            $arrTime = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::tr[1]/following-sibling::tr[1]",
                                $root, true, "#\d+:\d+(?:[ap]m)?#i");
                        }

                        if ($s->getDepDate() == strtotime($arrTime, $date)) {
                            $email->removeItinerary($f);
                        }

                        $s->arrival()->date(strtotime($arrTime, $date));

                        if ($day = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]",
                                $root, true, "#^\s*\+\s*(\d+)\s*\w+$#iu")
                            || $day = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]",
                                $root, true, "#\s+\+\s*(\d+)\s*\w+$#iu")) {
                            $s->arrival()
                                ->date(strtotime('+' . $day . ' day', $s->getArrDate()));
                        }

                        if ($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]",
                                $root, true, "#" . $this->opt($this->t("next day arrival")) . "#iu")
                            || $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]",
                                $root, true, "#" . $this->opt($this->t("next day arrival")) . "#iu")) {
                            $s->arrival()
                                ->date(strtotime('+1 day', $s->getArrDate()));
                        }

                        $car = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Seat:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root, true, "#" . $this->opt($this->t("COACH")) . "([A-Z\d]+)\-#");

                        if (!empty($car)) {
                            $s->extra()->car($car);
                        }
                        $seats = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Seat:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $root, true);

                        if (preg_match_all("#" . $this->opt($this->t("SEAT")) . "([A-Z\d]+)(?:\s+|$|" . $this->opt($this->t("COACH")) . ")#",
                            $seats, $m)) {
                            $s->extra()->seats($m[1]);
                        }
                    }
                }
            }

            //##############
            //##   BUS   ### (examples: it-462112675.eml)
            //##############
            $xpathBus = "descendant::text()[{$this->eq($this->t("Departure:"))}]/ancestor::tr[following-sibling::tr[normalize-space()]][1]/following-sibling::tr[descendant::text()[{$this->eq($this->t("Arrival:"))}] and string-length(normalize-space())>13]/ancestor-or-self::tr[ancestor::*[1][{$this->contains($this->t("Bus:"))}] or contains(.,'Departure')][1]/ancestor::*[1][preceding::img[normalize-space(@src)][1][{$this->contains('icon-bus', '@src')}]]";
            $nodes = $this->http->XPath->query($xpathBus);

            if ($nodes->length > 0) {
                $bus = $email->add()->bus();
                $bus->general()->noConfirmation()->travellers($travellers);

                foreach ($nodes as $root) {
                    $s = $bus->addSegment();
                    $date = $this->normalizeDate($this->http->FindSingleNode("tr[normalize-space()][1]/*[2]/descendant::text()[normalize-space()][1]", $root));

                    if (preg_match("/\b({$this->opt($this->t("Confirmed"))})\b/iu", $this->http->FindHTMLByXpath('.', null, $root), $m)) {
                        $s->extra()->status($m[1]);
                    }

                    $s->departure()->name($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Departure:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root));
                    $s->arrival()->name($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Arrival:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root));

                    $timeDep = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Departure:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('at'))})][2]", $root, true, "/^(?:at\s+)?({$patterns['time']})$/i");
                    $s->departure()->date(strtotime($timeDep, $date));

                    $timeArr = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Arrival:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('at'))})][2]", $root, true, "/^(?:at\s+)?({$patterns['time']})$/i");
                    $s->arrival()->date(strtotime($timeArr, $date));
                    $overnight = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Arrival:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('at'))})][3]", $root, true, "/^\s*([\-\+]\s*\d)\s+\w+\s*$/ui");

                    if (!empty($overnight) && !empty($s->getArrDate())) {
                        $s->arrival()
                            ->date(strtotime($overnight . ' day', $s->getArrDate()));
                    }

                    $number = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Bus:'))}]/following-sibling::*[normalize-space()][1]", $root, true, "/^(?:.{2,}\s+)?(\d+)$/");
                    $s->extra()->number($number);
                }
            }
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProviders as $code => $params) {
            if (!empty($params['from']) && $this->striposAll($from, $params['from']) === true) {
                return true;
            }
        }

        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundSubject = false;

        foreach (self::$commonSubject as $cSubject) {
            if (strpos($headers["subject"], $cSubject) !== false) {
                $foundSubject = true;
            }
        }

        if ($foundSubject == false) {
            return false;
        }

        foreach (self::$detectProviders as $code => $params) {
            if (!empty($params['from']) && $this->striposAll($headers['from'], $params['from']) === true
                || !empty($params['uniqueSubject']) && $this->striposAll($headers['subject'], $params['uniqueSubject']) === true
            ) {
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
//        if (empty($body)) {
//            $body = $this->http->Response['body'];
//        }
        foreach ($this->reBody as $reBody) {
            if (stripos($body, $reBody) !== false
                || $this->http->XPath->query("//img[{$this->contains([$reBody, strtolower($reBody)], '@src')}]/@src")->length > 0
            ) {
                return true;
            }
        }

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false
                    || $this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        foreach ($this->reBodyAlt as $re) {
            if ($this->http->XPath->query("//a[{$this->contains($re[0])} and (contains(@href,'//services.tripcase.com/') or contains(@href,'documents.sabre.com'))]")->length > 0
                && strpos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($this->re("/^[-[:alpha:]]+,\s+(\d{1,2}\s*[[:alpha:]]+\s*\d{4}\b)/u", $parser->getDate()));
        $this->http->FilterHTML = false;
        $this->subject = $parser->getSubject();
        $body = str_replace(" ", " ", $this->http->Response["body"]); // bad fr char " :"
        $body = preg_replace("#\xE2\x80\x8B#", "", $body);
        $this->http->SetEmailBody($body);

        $body = $this->http->Response["body"];
        $detectTranslate = ['Departure:', 'ROOMS', 'Car Type:', 'Tour Code:'];

        foreach (self::$dictionary as $lang => $lDict) {
            foreach ($detectTranslate as $trans) {
                if (empty($lDict[$trans])) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($lDict[$trans])}]")->length > 0
                    || $this->striposAll($body, $lDict[$trans]) !== false
                ) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        if (empty($this->lang)) {
            $this->lang = "en";
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProviders as $code => $params) {
                if (!empty($params['uniqueSubject']) && $this->striposAll($parser->getSubject(), $params['uniqueSubject']) === true) {
                    $this->providerCode = $code;
                }
            }
        }

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProviderCode($this->http->Response["body"]);
        }

        $email->setType('It5045494' . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName(".*pdf");
        $it = [];

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->text .= $text;

            if ($text !== null
                && $this->striposAll($text, $this->tPdf('Total Amount'))) {
                if (preg_match("/{$this->opt($this->tPdf('Total Amount'), true)}\s+(?<currency>[A-Z]{3,4})[ ]+(?<amount>\d[,.\'\d ]*)/", $text, $m)) {
                    if (isset($it['Currency']) && $it['Currency'] != $m['currency']) {
                        $it = [];

                        break;
                    }
                    $it['Currency'] = $m['currency'];
                    $it['TotalCharge'] = isset($it['TotalCharge']) ? $it['TotalCharge'] + $this->normalizeAmount($m['amount']) : $this->normalizeAmount($m['amount']);

                    // BaseFare
                    if (preg_match("/^[ ]*(?:{$this->opt($this->tPdf('Fare'), true)}|{$this->opt($this->tPdf('Equivalent'), true)})[ ]{2,}{$this->addSpacesWord(preg_quote($m['currency'], '/'))}[ ]+(?<amount>\d[,.\'\d ]*)/m", $text, $matches)) {
                        $it['BaseFare'] = isset($it['BaseFare']) ? $it['BaseFare'] + $this->normalizeAmount($matches['amount']) : $this->normalizeAmount($matches['amount']);
                    }

                    // Fees
                    if (preg_match("/^([ ]*{$this->opt($this->tPdf('Taxes/Fees/Carrier'), true)}[\s\S]+?)^[ ]*{$this->opt($this->tPdf('Total Amount'), true)}/m", $text, $m2)
                        && preg_match_all('/(?:^[ ]*|[ ]{2})' . $this->addSpacesWord(preg_quote($m['currency'], '/')) . '[ ]+(?<charge>\d[,.\'\d ]*?)[ ]*(?<name>[A-Z][A-Z\d ]+?)[ ]*(?:\(|$)/m', $m2[1], $matches, PREG_SET_ORDER)
                    ) {
                        // USD 10.00 E2 (INFRAST RUCT URE TAX)
                        foreach ($matches as $fee) {
                            $it['Fees'][] = ['Name' => $fee['name'], 'Charge' => $this->normalizeAmount($fee['charge'])];
                        }
                    }
                }
            }
        }

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
        }

        $this->parseHtml($email);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

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

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
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
            || preg_match("/^\s*Cancel (?<dPrior>\d+) day(?:|s|\(s\)) prior to arrival(?: to avoid a penalty\.?)?\s*$/ui", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['dPrior'] . ' days');
        }
    }

    private function uniqueFlightSegments(Flight $f): void
    {
        $segments = $f->getSegments();

        if (empty($segments)) {
            return;
        }

        foreach ($segments as $key => $s) {
            for ($i = $key - 1; $i >= 0; $i--) {
                if (empty($segments[$i])) {
                    continue;
                }

                $condition1 = $s->getNoFlightNumber() === $segments[$i]->getNoFlightNumber()
                    && $s->getFlightNumber() === $segments[$i]->getFlightNumber();
                $condition2 = $s->getNoDepCode() === $segments[$i]->getNoDepCode()
                    && $s->getDepCode() === $segments[$i]->getDepCode()
                    && $s->getNoArrCode() === $segments[$i]->getNoArrCode()
                    && $s->getArrCode() === $segments[$i]->getArrCode();
                $condition3 = $s->getNoDepDate() === $segments[$i]->getNoDepDate()
                    && $s->getDepDate() === $segments[$i]->getDepDate();

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($s->getSeats())) {
                        if (!empty($segments[$i]->getSeats())) {
                            $segments[$i]->setSeats(array_unique(
                                array_merge($segments[$i]->getSeats(), $s->getSeats())
                            ));
                        } else {
                            $segments[$i]->setSeats($s->getSeats());
                        }
                    }
                    $f->removeSegment($s);
                    unset($segments[$key]);

                    break;
                }
            }
        }
    }

    private function getProviderCode(string $body): ?string
    {
        if (!empty($this->providerCode)) {
            return $this->providerCode;
        }

        foreach (self::$detectProviders as $code => $prov) {
            if (!empty($prov['href']) && $this->http->XPath->query("//a[{$this->contains($prov['href'], '@href')}]")->length > 0) {
                return $code;
            }

            if (empty($prov['body'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($prov['body'])}]")->length > 0
                || $this->striposAll($body, $prov['body'])
            ) {
                return $code;
            }
        }

        return null;
    }

    private function striposAll(?string $text, $needle): bool
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

    private function nextText($field, $root = null, $n = 1, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field, 'translate(.,"⋅","")')}])[1]/following::text()[normalize-space(.)][{$n}]",
            $root, true, $re);
    }

    private function getField($field, $root = null, ?string $re = "/:\s*([^:\s].*)$/", $n = 1)
    {
        return $this->http->FindSingleNode("(descendant::text()[{$this->eq($field)}])[{$n}]/ancestor::tr[1]", $root, true, $re);
    }

    private function getFlightArrName($num, Email $email): ?string
    {
        /** @var Flight $it */
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'flight') {
                /** @var FlightSegment[] $segs */
                $segs = $it->getSegments();

                foreach ($segs as $seg) {
                    if ($seg->getFlightNumber() == $num) {
                        return $seg->getArrName();
                    }
                }
            }
        }

        return null;
    }

    private function getProviderCodeByIata(?string $iata): ?string
    {
        if (!isset(self::$detectProviders) || empty($iata)) {
            return null;
        }

        foreach (self::$detectProviders as $providerCode => $params) {
            if (!is_array($params) || empty($params['iata'])) {
                continue;
            }

            if ($params['iata'] === $iata) {
                return $providerCode;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function tPdf($word)
    {
        if (!isset(ETicketPdf::$dictionary[$this->lang]) || !isset(ETicketPdf::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return ETicketPdf::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }

        //$this->logger->debug('$instr = ' . print_r($instr, true));

        $in = [
            "/^at\s+(.+)$/i",
            "#^(?<week>[^\d\s.,]+),\s+(\d+)\s+([^\d\s.,]+)$#",
            "#^(?<week>[^\d\s.,]+),\s+(\d+)\s+([^\d\s.,]+)\s+-$#",
            "#^(?<week>[^\d\s.,]+),\s+(\d+)\s+([^\d\s.,]+)\s+-\s+[^\d\s.,]+,\s+(\d+)\s+([^\d\s.,]+)$#",
            "#^(?<week>[^\d\s.,]+),\s+(\d+)\s+([^\d\s.,]+)\s+(\d{4})$#",

            "/^(\d{1,2})\s+([[:alpha:]]+)$/u",
            "/^(\d+:\d+\s*[ap]m)[,\s]+[[:alpha:]]+\s*\d{1,2}$/iu", // 3:00PM, Aug 11
            "#^\w+\,\s+(\d+\s*\w+\s*\d{4})\s*[\d\:]+\s*\-?\d+$#", //Sun, 7 Mar 2021 14:02:27 -0500
            "#^(\d+:\d+\s*(?:[ap]m)?)\s+[^\d\s.,]+,\s+(\d+)\s+([^\d\s.,]+)$#i",
            //08 month 29 day
            '#(\d+) month (\d+) day#',
            //Terça-feira, 27 Janeiro, 10:00
            "#^(?<week>[^\s\d.,]+), (\d+) ([^\s\d.,]+), (\d+:\d+)$#",

            // Sunday, 26 February at 1:09PM
            //Sunday, 01 June, 6:00AM
            "#^(?<week>[^\s\d.,]+), (\d+) ([^\s\d.,]+)\s*(?:,|at)\s*(\d+:\d+\s*[AP]M)$#",
            //Ngày 16 Tháng 08
            '#^\s*Ngày\s*(\d+)\s*Tháng\s*(\d+)\s*$#ui',
            //14 сен 2020
            //            '#^\s*Ngày\s*(\d+)\s*Tháng\s*(\d+)\s*$#ui',
            "#^(?<week>[^\d\s.,]+),\s+([^\d\s.,]+)\s+(\d+)\s*$#",
        ];
        $out = [
            "$1",
            "$2 $3 %Y%",
            "$2 $3 %Y%",
            "$2 $3 %Y%",
            "$2 $3 $4",

            "$1 $2 %Y%",
            "$1",
            "$1",
            "$2 $3 %Y%, $1",
            "$2.$1.%Y%",
            "$2 $3 %Y%, $4",

            "$2 $3 %Y%, $4",
            "$1.$2.%Y%",
            "$3 $2 %Y%",
        ];
        $str = $instr;

        foreach ($in as $i => $pattern) {
            if (preg_match($pattern, $instr)) {
                $str = preg_replace($pattern, $out[$i], $instr);

                break;
            }
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#u", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d',
                strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", $relDate ? date('Y', $relDate) : '', $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang) ?? WeekTranslate::number1($m['week'],
                        'fr');

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(str_replace("%Y%", '', $str), $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/^(\d{2})(\d{2})$/', '$1:$2', $s); // 1245    ->    12:45

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            $s = preg_quote($s, '/');

            return $addSpaces ? $this->addSpacesWord($s) : $s;
        }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function addSpacesWord($text)
    {
        return preg_replace("#(\w)#u", '$1 *', $text);
    }

    private function amount($s)
    {
        $s = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s)));

        if (is_numeric($s)) {
            return (float) $s;
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

    private function normalizeRentalProvider(?string $string): ?string
    {
        $string = trim($string);

        foreach (self::$rentalProviders as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }
}
