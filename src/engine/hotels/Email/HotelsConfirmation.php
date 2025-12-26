<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: add collecting roomRate (example: it-8980691.eml)

class HotelsConfirmation extends \TAccountChecker
{
    public $mailFiles = "hotels/it-17051081.eml, hotels/it-183559773.eml, hotels/it-183781462.eml, hotels/it-185692881.eml, hotels/it-186333289.eml, hotels/it-187272991.eml, hotels/it-20523420.eml, hotels/it-21390676.eml, hotels/it-27398216.eml, hotels/it-2757048.eml, hotels/it-2758325.eml, hotels/it-2758547.eml, hotels/it-2763933.eml, hotels/it-2772066.eml, hotels/it-2794784.eml, hotels/it-2805370.eml, hotels/it-2805374.eml, hotels/it-2893171.eml, hotels/it-2897851.eml, hotels/it-2929135.eml, hotels/it-2929469.eml, hotels/it-2945175.eml, hotels/it-3033279.eml, hotels/it-3034683.eml, hotels/it-3034684.eml, hotels/it-3049153.eml, hotels/it-30734064.eml, hotels/it-3129042.eml, hotels/it-3321814.eml, hotels/it-3328798.eml, hotels/it-3329995.eml, hotels/it-3330067.eml, hotels/it-3351744.eml, hotels/it-3354879.eml, hotels/it-3370078.eml, hotels/it-45386516.eml, hotels/it-56731979.eml, hotels/it-57556199.eml, hotels/it-59763100.eml, hotels/it-66969942.eml, hotels/it-6742935.eml, hotels/it-6843798.eml, hotels/it-8622267.eml, hotels/it-8670899.eml, hotels/it-8695601.eml, hotels/it-8980691.eml, hotels/it-9135389.eml, hotels/it-97261021.eml";

    public static $detectHeaders = [
        'hotels' => [
            'from' => ['@hotels.com', '.hotels.com'],
            'subj' => [
                'cs'  => 'Hotels.com – potvrzení rezervace',
                'pt'  => ' da Hoteis.com -',
                'pt2' => 'Confirmação da reserva',
                'pt3' => 'Confirmação de reserva',
                'pt4' => 'Sua reserva foi cancelada - n° de confirmação:',
                'fr'  => 'Hotels.com - Confirmation de réservation',
                'fr2' => 'Confirmation de la réservation',
                'fr3' => 'Votre réservation a été annulée - N° de confirmation',
                'nl'  => 'Boekingsbevestiging van Hotels.com',
                'de'  => 'Hotels.com-Buchungsbestätigung:',
                'sv'  => 'Hotels.coms bokningsbekräftelse',
                'no'  => 'Hotels.com-bestillingsbekreftelse',
                'is'  => 'Hotels.com bókunarstaðfesting',
                'zh'  => 'Hotels.com 訂房確認',
                'zh2' => 'Hotels.com 預訂確認號碼',
                'zh3' => 'Hotels.com 好订网 预订确认编号：',
                'zh4' => 'Hotels.com 預訂 (確認編號：',
                'fi'  => 'Hotels.com varausvahvistus',
                'ru'  => 'Номер подтверждения бронирования на Hotels.com',
                'es'  => 'Número de confirmación de reserva de Hoteles.com',
                'es2' => 'Confirmación de la reservación de Hoteles.com:',
                'da'  => 'Reservationsbekræftelse fra Hotels.com',
                'en'  => 'Hotels.com booking confirmation',
                'en2' => 'has been cancelled',
                'en3' => 'has been canceled',
                'en4' => 'Hotels.com reservation confirmation',
                'ko'  => 'Hotels.com 예약 확인',
                'tr'  => 'Hotels.com rezervasyon onayı',
                'ja'  => 'Hotels.com 予約確認番号',
                'hr'  => 'Hotels.com – potvrda rezervacije',
                'it'  => 'Hotels.com - Conferma della prenotazione',
                'sk'  => 'Hotels.com potvrdenie rezervácie',
                'th'  => 'Hotels.com หมายเลขยืนยันการจอง',
                'he'  => 'מספר אישור הזמנה',
                // pl
                'Numer potwierdzenia rezerwacji Hotels.com:',
                // it
                'Conferma della prenotazione Hotels.com',
                // fi
                'Hotels.com – varausvahvistus',
                // id
                'Konfirmasi reservasi Hotels.com dengan nomor',
            ],
        ],
        'aeroplan' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "Booking confirmation",
            ],
        ],
        'jetairways' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "Jet Airways reservation confirmation",
            ],
        ],
        'delta' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "Delta booking confirmation",
                "Your reservation has been canceled - confirmation no",
            ],
        ],
        'edreams' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "eDreams reservation confirmation",
                "Confirmación de la reserva de eDreams Prime",
            ],
        ],
        'mileageplus' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "United Hotels booking confirmation",
            ],
        ],
        'cheaphotels' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "Cheaphotels booking confirmation",
            ],
        ],
        'lanpass' => [
            'from' => ['hotelcustomercare.com'],
            'subj' => [
                "Confirmação da reserva", // Confirmação da reserva 8060767456164 da Multiplus: Residence Inn by Marriott Orlando at SeaWorld - Orlando
            ],
        ],
    ];

    private $lang = '';
    private $providerCode;

    private $detectCompany = [
        'aeroplan'    => ['hotels.aircanada.com'],
        'jetairways'  => ['hotels.jetairways.com'],
        'delta'       => ['Delta Confirmation Number', 'www.hotels-delta.com'],
        'edreams'     => ['accommodation.edreams.net', 'eDreams confirmation number', 'Número de confirmación de eDreams Prime'],
        'mileageplus' => ['United Hotels confirmation number', 'hotels.united.com'],
        'cheaphotels' => ['Cheaphotels confirmation number', 'secure.cheaphotels.com'],
        'lanpass'     => ['Número de confirmação da Multiplus', 'hoteis.pontosmultiplus.com.br'],
        'hotels'      => ['Hotels.com'],
    ];
    private $detectBody = [
        'en' => [
            'Hotel Details', // it-2758325.eml
            ['your booking is', 'Thanks for booking with us'],
            'Hotels.com confirmation number',
            'Delta confirmation number',
            'By cancelling this booking you will not earn any Hotels.com', // it-57556199.eml
            'By canceling this booking you will not earn any Hotels.com',
            'By cancelling this reservation you will not earn any Hotels.com',
            'By canceling this reservation you will not earn any Hotels.com',
            'We want to let you know that your booking has been cancelled',
            'We want to let you know that your booking has been canceled',
            'as requested, we’ve canceled your booking',
            'Property Details',
            'we’ve cancelled your booking', 'we’ve canceled one of the rooms in your booking',
            'Below is a summary of the cancellation',
            'United Hotels confirmation number',
        ],
        'pt' => [
            'Detalhes do hotel', // it-2763933.eml, it-6742935.eml, it-6843798.eml
            'Informações sobre o estabelecimento',
            'Informações sobre o hotel',
            'Ao cancelar esta reserva, você não acumula noites do Hoteis.com',
            'sua reserva está confirmada',
            'como solicitado, cancelamos a sua reserva',
            ', a sua reserva foi cancelada.',
            'A reserva está confirmada e você ',
            'atendendo à sua solicitação, a reserva foi cancelada',
            'Sua reserva',
            'A reserva está confirmada e o pagamento',
            'cancelamos o último quarto da sua reserva',
            'cancelamos um dos quartos da sua reserva',
            'cancelámos a sua reserva de acordo com o seu pedido',
            'Política de cancelamento',
        ],
        'fr' => [
            'Infos hôtel', // it-2893171.eml
            'Détails de l’établissement',
            'Détails sur l’établissement',
            'Numéro de confirmation',
            'demandé, nous avons annulé votre réservation',
            'Affichez vos réservations',
        ],
        'nl' => [
            'Hoteloverzicht', // it-2897851.eml
            'bevestigingsnummer',
            'Annuleringsbeleid',
        ],
        'de' => [
            'Hotelinformationen', // it-2929135.eml
            'Stornierungsbedingungen', // it-8622267.eml, it-8670899.eml, it-8695601.eml
        ],
        'sv' => [
            'Hotelluppgifter', // it-2929469.eml
            'Information om rummet',
            'precis som du bad om har vi avbokat din bokning',
            'Information om enheten',
            'Avbokningsregler',
        ],
        'no' => [
            'Hotelldetaljer', // it-3129042.eml, it-3321814.eml
            'Informasjon om overnattingsstedet',
            'Avbestillingen er gjennomført, så du behøver ikke gjøre noe mer',
            'Avbestillingsregler',
        ],
        'is' => [
            'Hótelupplýsingar', // it-66969942.eml
        ],
        'zh' => [
            '店詳細資料', // it-3033279.eml
            'Hotels.com 確認號碼',
            'Hotels.com 確認編號',
            'Hotels.com 好订网 确认编号',
            "我們已按要求取消了你的預訂",
        ],
        'fi' => [
            'Hotellitiedot', // it-3034683.eml
            'vahvistusnumero',
            'Tulopäivä',
        ],
        'ru' => [
            'Сведения об отеле', // it-3328798.eml, it-3329995.eml, it-3330067.eml, it-3354879.eml
            'Информация об отеле',
            'Сведения о жилье',
            'мы отменили последний номер в бронировании по вашему запросу',
            'мы отменили бронирование по вашему запросу',
        ],
        'es' => [
            'Detalles del hotel', // it-3351744.eml
            'Detalles del establecimiento',
            'Detalles de la habitación', // it-3370078.eml
            'hemos cancelado tu reserva',
            'Te confirmamos que ya cancelamos tu reservación',
            'cancelamos tu reservación de acuerdo',
            'Detalles de pago',
        ],
        'da' => [
            'Hoteloplysninger',
            'Oplysninger om overnatningsstedet',
            'anmodning har vi afbestilt din reservation',
            'Afbestillingspolitik',
        ],
        'it' => [
            'Numero di conferma', // it-9135389.eml
            'abbiamo cancellato la prenotazione',
        ],
        'ko' => [
            'Hotels.com 확인 번호', // it-17051081.eml
            '요청하신 대로 예약을 취소해 드렸습니다',
            '체크인',
        ],
        'tr' => [
            'Otel Bilgileri',
        ],
        'ja' => [
            'ホテルの詳細', // it-21390676.eml
            'キャンセルの概要は以下のとおりです',
            '宿泊施設の詳細',
        ],
        'hr' => [
            'Detalji hotela', // it-20523420.eml
        ],
        'he' => [
            'פרטי מלון', // it-27398216.eml
            'פרטי הנכס',
            'יעוץ לגבי נסיעה',
        ],
        'hu' => [
            'A szállás adatai',
            'A szálláshely részletei',
        ],
        'pl' => [
            'Szczegóły dotyczące hotelu',
            'Szczegóły hotelu',
            'Twoja rezerwacja została zagwarantowana',
            'Szczegóły obiektu',
        ],
        'el' => [
            'Αριθμός επιβεβαίωσης',
        ],
        'sk' => [
            'Hotels.com číslo potvrdenia',
        ],
        'th' => [
            'หมายเลขยืนยัน Hotels.com',
        ],
        'id' => [
            'Rincian Hotel',
        ],
        'cs' => [
            'Číslo potvrzení Hotels.com',
        ],
    ];

    private static $dictionary = [
        'en' => [
            "confirmation number" => [
                "confirmation number",
                "Booking confirmation",
                "Hotels.com confirmation number",
                "Hotels.com Confirmation Number",
                "Delta confirmation number",
            ],
            "Hotel Details" => ["Hotel Details", "Property Details", 'confirmation.mail.location.details.unhotelling'],
            "cancelled"     => [
                "your booking has been cancelled", "your booking has been canceled",
                "we’ve cancelled your booking", "we’ve canceled your booking",
                "we’ve cancelled the last room in your booking", "we’ve canceled the last room in your booking",
                "we’ve cancelled one of the rooms in your booking", "we’ve canceled one of the rooms in your booking",
            ],
            "StatusCancelled"            => "your booking has been (cancelled|canceled)\.",
            "The room is in the name of" => ["The room is in the name of", "The unit is in the name of"],
            "Check in"                   => ["Check-in", "Check in"],
            "Check out"                  => ["Check-out", "Check out"],
            "Room:"                      => ["Room:", "Room ", "Room"],
            "room"                       => ["room", "unit"],
            "kids"                       => ["kids", "child"],
            "Payment details"            => ["Payment details"],
            "Taxes & fees"               => ["Taxes & fees", "Tax recovery charges and service fees", "Tax recovery charges", 'Taxes'],
            "Amount paid"                => [
                "Amount paid",
                "Total amount paid",
                "Amount to pay at hotel",
                "Total to be charged by the hotel",
                "Total to be charged by the property",
                "Amount to pay the property",
                "Total",
                "Amount paid after booking",
                "Total price",
                "confirmation.mail.booking_summary.amount.postpay.unhotelling",
                "Amount to pay at hotel in their local currency",
                "Total to be charged by the property in its local currency",
            ],
            "Payment schedule"                => [
                "Payment schedule",
            ],
            "Room details"        => ["Room details", "Facilities"],
            "Room detailsH1"      => ["Room details", "Unit Details"],
            "nonRefundableRegExp" => "Non-refundable",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => [
                '/Free (?i)cancell?ation[. ]+until\s*(?<date>\d+[-\/]\d+[-\/]\d+)\W*If you change or cancell? your booking after\s*(?<time>\d+:\d+\s*(?:[AP]M)?), \1 (?:property’s local time\s*)?\(.+\) you will be charged for \d+ night \(including tax\)/',
                '/^(?:Free cancell?ation|Fully refundable)[. ]+until\s*(?<time>\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?), (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})/im',
                '/^Free cancell?ation[. ]+until\s*(?<date>[-\d\/]+(?:\s*\d{1,2}:\d{2})?)/im',
                '/If you change or cancell? your booking after (?<time>\d{1,2}:\d{2}), (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})/i',
            ],
            "membershipNumber"                          => ["Your Hotels.com® Rewards membership number is", "Your Hotels.com™ Rewards membership number is"],
            "Occupancy"                                 => ["Occupancy", "Guests"],
            "Hotels.com® Rewards reward* night applied" => ["Hotels.com® Rewards reward* night applied", "Hotels.com™ Rewards reward* night applied", 'Hotels.com® Rewards free night applied'],
            //            "Important notices"                         => "",
        ],
        'pt' => [
            "confirmation number" => [
                "Número de confirmação da Hoteis.com",
                "Número de confirmação do",
                "Número de confirmação da",
                "Nº de confirmação da reserva",
                "N.º de confirmação da reserva",
            ],
            "cancelled"                  => ["a sua reserva foi cancelada", "cancelamos a sua reserva", "a reserva foi cancelada", "cancelamos o último quarto da sua reserva", "cancelamos um dos quartos da sua reserva",
                'cancelámos a sua reserva de acordo com o seu pedido', ],
            "StatusCancelled"            => ["a\s+sua\s+reserva\s+foi\s+(\D+)[.]\s+Veja", "como solicitado, (cancelamos) a sua reserva\."],
            "The room is in the name of" => ["O quarto está no nome de", "A unidade está no nome de", 'A reserva do quarto está em nome de'],
            "Dear "                      => "Bom dia, ",
            "Hotel Details"              => ["Detalhes do hotel", "Informações sobre o estabelecimento", "Detalhes da acomodação"],
            "Check in"                   => "Check-in",
            "Check out"                  => "Check-out",
            "Phone:"                     => "Telefone:",
            "Your stay"                  => ["Sua estadia", "A sua estadia"],
            "room"                       => ["quarto", "unidade"],
            "Cancellation policy"        => "Política de cancelamento",
            "Room:"                      => ["Quarto", "Quarto:", "Unidade", "Unidade:"],
            "Room @"                     => "Quarto @",
            "adult"                      => ["adult", "adultos"],
            "kids"                       => ["crianças", "criança"],
            "Preferences"                => "Preferências",
            "Taxes & fees"               => ["Impostos e taxas", "Impostos e Taxas", "Impostos"],
            "Amount paid"                => [
                "Valor total a ser pago no hotel",
                "Valor a ser pago no hotel",
                "Valor pago",
                "Montante a pagar no estabelecimento",
                "Total a ser cobrado pela acomodação",
                "Montante pago",
                "Valor a ser pago na acomodação",
                "Total a ser cobrado pelo hotel",
                "Preço total",
            ],
            "Payment schedule"           => "Pagamentos",
            "Room details"               => ["Instalações", "Comodidades"],
            "Room detailsH1"             => ["Informações sobre o quarto", "Detalhes da unidade"],
            "Free cancellation until"    => 'Cancelamento grátis até',
            "nonRefundableRegExp"        => "Não reembolsável",
            "#Free cancellation...#"     => [
                '#Cancelamento grátis até(?: ao dia)? (?<date>\d+\/\d+\/\d+)\W*Se(?: você)? alterar ou cancelar(?: a)? sua reserva (?:depois de|após as) (?<time>\d+:\d+)#u',
                '#Se você alterar ou cancelar a sua reserva após (?<time>\d+:\d+), em (?<date>\d+\/\d+\/\d+) \(.+\), deverá pagar uma taxa de \d{1,3}%#u',
                "#^Cancelamento grátis até (?<date>.+) \(GMT#",
                '#^Cancelamento grátis até (?<date>[\d\/]+(?:\s*\d+:\d+)?)$#m',
                '#^Cancelamento grátis até (?<time>\d+:\d+), (?<date>\d+\/\d+\/\d+) \(.+\)\.#m',
                '#^(?:Cancelamento grátis|Totalmente reembolsável) até (?<time>\d+:\d+), (?<date>\d+\/\d+\/\d+) \(.+\)\.#m',
            ],
            "membershipNumber"                          => ["O seu número de membro Hoteis.com® Rewards é:", "Seu número de associado do Hoteis.com™ Rewards é"],
            "Occupancy"                                 => ["Occupancy", "Hóspedes"],
            "Hotels.com® Rewards reward* night applied" => ["Noite de recompensa* do Hoteis.com™ Rewards aplicada", "Diária grátis do Hoteis.com™ Rewards aplicada"],
            "Important notices"                         => "Avisos importantes",
            "Price for room "                           => "Preço para o quarto ",
            "Price per room per night"                  => "Preço por quarto, por noite",
        ],
        'fr' => [
            "confirmation number" => ["Numéro de confirmation", "Numéro de confirmation Hotels.com", "Confirmation de la réservation nº", 'Confirmation de réservation n°', "numéro de confirmation Hotels.com"],
            "Dear "               => "Bonjour ",
            "Hotel Details"       => [
                "Infos hôtel",
                "Détails de l’établissement",
                'Détails sur l’établissement',
                'Détails de l’hôtel',
            ],
            "cancelled"                  => ["nous avons annulé votre réservation", "procédé à l’annulation de votre réservation", "réservation a bien été annulée"],
            "StatusCancelled"            => "nous avons (annulé) votre réservation",
            "The room is in the name of" => ["La chambre est réservée au nom de la personne suivante :", "Cette chambre est réservée au nom de"],
            "Check in"                   => "Arrivée",
            "Check out"                  => "Départ",
            "Phone:"                     => ["Téléphone :"],
            "Your stay"                  => "Votre séjour",
            "room"                       => ["chambre", "unité"],
            "Cancellation policy"        => ["Conditions d’annulation", "Politique d’annulation"],
            "Room:"                      => ["Chambre"],
            "Room @"                     => "NOTRANSLATED",
            "adult"                      => ["adult", "adultes"],
            "kids"                       => "enfants",
            "Preferences"                => "Préférences",
            "Taxes & fees"               => ["Taxes et frais", "Taxes"],
            "Amount paid"                => [
                "Montant total facturé par l’hôtel",
                'Montant total payé',
                'Montant à payer à l’établissement dans sa devise locale',
                "Montant payé",
                "Montant à payer à l’établissement",
                "Prix total",
                "Montant total",
                "Montant total que l’hôtel facturera dans sa devise locale",
            ],
            //            "Payment schedule"           => "",
            "Room details"            => ["Inclus", "Services et équipements", "Installations", "Détails de la chambre"],
            "Room detailsH1"          => ["Détails de la chambre", "Informations sur la chambre"],
            "nonRefundableRegExp"     => "Non remboursable",
            "Free cancellation until" => 'Annulation gratuite jusqu',
            "#Free cancellation...#"  => [
                '#Annulation gratuite jusqu’au (?<date>\d+\/\d+\/\d+)\W*Si vous modifiez ou annulez votre réservation après le \1 à (?<time>\d+:\d+) \(.+\), vous devrez payer le montant de \d+ nuit \(taxes comprises\)#u',
                '#Annulation sans frais jusqu’au (?<date>\d+\/\d+\/\d+)\W*Si vous modifiez ou annulez votre réservation après (?<time>\d+:\d+) le \1, [^.]*?, vous devrez payer un montant équivalant à \d+ nuit \(taxes comprises\)#u',
                '#Annulation gratuite. jusqu’à (?<time>\d+:\d+) le (?<date>\d+\/\d+\/\d+)#u',
            ],
            "membershipNumber"                          => ["Votre numéro de membre Hotels.com™ Rewards est le", "Votre Hotels.com® Rewards numéro d’inscription est le"],
            "Occupancy"                                 => ["Personnes", "Clients"],
            "Hotels.com® Rewards reward* night applied" => "Nuit gratuite Hotels.com® Rewards appliquée",
            "Important notices"                         => "Remarques importantes",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'nl' => [
            "confirmation number"        => ["Hotels.com-bevestigingsnummer", "-bevestigingsnummer", "Boekingsbevestigingnr."],
            "Dear "                      => "Beste ",
            "Hotel Details"              => ["Hoteloverzicht", "Hoteldetails", "Accommodatiedetails"],
            "cancelled"                  => "we je boeking geannuleerd",
            "StatusCancelled"            => "(geannuleerd)",
            "The room is in the name of" => "De kamer staat op naam van",
            "Check in"                   => "Inchecken",
            "Check out"                  => "Uitchecken",
            "Phone:"                     => "Telefoon:",
            "Your stay"                  => ["Je verblijf", "Uw verblijf"],
            "room"                       => ["kamer", "unit"],
            "Cancellation policy"        => "Annuleringsbeleid",
            "Room:"                      => ["Kamer"],
            "Room @"                     => ["Kamer @"],
            "adult"                      => ["volwass", "volwassenen"],
            "kids"                       => "NOTTRANSLATED",
            "Preferences"                => "Voorkeuren",
            "Taxes & fees"               => ["Belastingen en toeslagen", "Belastingen"],
            "Amount paid"                => ["Betaald totaalbedrag", "Totaalbedrag te voldoen bij", "Totaalbedrag door het hotel",
                "Totaalbedrag, te voldoen in het hotel", "Totaalprijs",
                "Bedrag te betalen bij de accommodatie", ],
            "Payment schedule"           => "Betaalschema",
            "Room details"               => ["Inbegrepen", "Faciliteiten", "Voorkeuren"],
            "Room detailsH1"             => "Kamergegevens",
            "nonRefundableRegExp"        => "(?:Geen restitutie|Speciaal tarief zonder restitutie\.)",
            "Free cancellation until"    => 'Gratis annulering uiterlijk tot',
            "#Free cancellation...#"     => [
                "#^Gratis annuleren kan tot (?<date>[\d\-]+(?:\s*\d+:\d+)?)\s*\(.+\)#u",
                '/Bij een boeking die na (?<time>\s*\d+:\d+)[ ]*uur op[ ]*(?<date>[\d\-\/]+)/u',
                '/Gratis annulering\.? uiterlijk tot (?<time>\s*\d+:\d+)\,[ ]*(?<date>[\d\-\/]+)\s*\([A-Z]{2,4}/u',
            ],
            "membershipNumber"                          => "Je Hotels.com® Rewards lidmaatschapsnummer is",
            "Occupancy"                                 => "Gasten",
            "Hotels.com® Rewards reward* night applied" => "Gratis nacht van Hotels.com® Rewards toegepast",
            "Important notices"                         => "Belangrijke informatie",
            "Price for room "                           => "Prijs voor kamer ",
            //"Price per room per night" => "";
        ],
        'de' => [
            "confirmation number"        => ["-Bestätigungsnummer", "Hotels.com-Bestätigungsnummer", "Buchungsbestätigungsnr."],
            "Dear "                      => "Liebe(r)",
            "Hotel Details"              => ["Hotelinformationen", "Unterkunftsdetails"],
            "cancelled"                  => "wir Ihre Buchung storniert",
            "StatusCancelled"            => "(storniert)",
            "The room is in the name of" => "Die Buchung der Wohneinheit wurde unter dem Namen",
            "Check in"                   => "Anreise",
            "Check out"                  => "Abreise",
            "Phone:"                     => "Telefon:",
            "Your stay"                  => ["Ihr Aufenthalt", "Aufenthalt"],
            "room"                       => ["Zimmer", "Wohneinheit"],
            "Cancellation policy"        => ["Stornierungsbedingungen:", "Stornierungsbedingungen"],
            "Room:"                      => ["Zimmer", "Wohneinheit"],
            "Room @"                     => "Zimmer @",
            "adult"                      => ["Erwachsener", "Erwachsene"],
            "kids"                       => "Kinder",
            "Preferences"                => ["Zimmerpräferenzen:", "Zimmerpräferenzen"],
            "Taxes & fees"               => ["Steuern und Gebühren", "Steuern"],
            "Amount paid"                => ["Gezahlter Gesamtbetrag", "Gesamtbetrag (im Hotel fällig)", 'In der Unterkunft zu zahlender Betrag',
                'Gesamtpreis', ],
            "Payment schedule"           => "Zahlungsplan",
            "Room details"               => ["Inklusive", "Ausstattung"],
            "Room detailsH1"             => "Zimmerdetails",
            "nonRefundableRegExp"        => "Nicht erstattungsfähig",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => [
                '#Kostenlose Stornierung bis zum (?<date>\d+\.\d+\.\d+)\W*Wenn Sie Ihre Buchung nach dem \1(?: um|,) (?<time>\d+:\d+) Uhr \(.+\) ändern oder stornieren, #',
                '#Voll erstattungsfähig bis (?<date>\d+\.\d+\.\d+), (?<time>\d+:\d+) Uhr \(.+\)\.#',
            ],
            "#Non-refundable-details#"                  => '#^Wenn Sie Ihre Buchung nach dem (?<date>\d+\.\d+\.\d+) um (?<time>\d+:\d+) Uhr \(.+\) ändern oder stornieren, wird eine Gebühr in Höhe von 100 % erhoben.\s*Wenn Sie Ihre Buchung bis spätestens \1 um \2 Uhr \(.+\) ändern oder stornieren, wird eine Gebühr in Höhe von \d+% erhoben.#',
            "membershipNumber"                          => ["Ihre Hotels.com™ RewardsMitgliedsnummerlautet", "Ihre Hotels.com® RewardsMitgliedsnummerlautet"],
            "Occupancy"                                 => ["PersonenS", "Personen"],
            "Hotels.com® Rewards reward* night applied" => "Hotels.com® Rewards-Prämiennacht* eingelöst",
            "Important notices"                         => "Wichtige Hinweise:",
            "Price for room "                           => "Preis für Zimmer ",
            "Price per room per night"                  => "Preis pro Zimmer pro Nacht",
        ],
        'sv' => [
            "confirmation number"        => ["Bekräftelsenummer från", "Bokningsbekräftelse #"],
            "Dear "                      => "Hej ",
            "Hotel Details"              => ["Hotelluppgifter", "Boendeinformation"],
            "cancelled"                  => "vi avbokat din bokning",
            "StatusCancelled"            => "vi (avbokat) din bokning",
            "The room is in the name of" => "Rummet är bokat i namnet",
            "Check in"                   => ["Checka in"],
            "Check out"                  => ["Checka ut"],
            "Phone:"                     => "Telefon:",
            "Your stay"                  => "Din vistelse",
            "room"                       => ["rum", "enhet"],
            "Cancellation policy"        => "Avbokningsregler",
            "Room:"                      => "Rum",
            "Room @"                     => "NOTTRANSLATED",
            "Occupancy"                  => ["Gäster"],
            "adult"                      => ["vuxna", "vuxen"],
            "kids"                       => "barn",
            "Preferences"                => ["Önskemål", "Bekvämligheter"],
            "Taxes & fees"               => "Skatter och avgifter",
            "Amount paid"                => ["Betalt totalbelopp", 'Totalbelopp som debiteras av hotellet', 'Betalt belopp',
                'Totalpris', ],
            "Payment schedule"                    => "Betalningsplan",
            "Bon de réduction appliqué"           => "Kupong tillämpad",
            "Room details"                        => ["Inkluderar", "Bekvämligheter"],
            "Room detailsH1"                      => ["Information om rummet", "Information om enheten"],
            "nonRefundableRegExp"                 => "^\s*Ingen återbetalning",
            "Free cancellation until"             => "Gratis avbokning till och med",
            "#Free cancellation...#"              => [
                "#Gratis avbokning till och med (?<date>\d+\.\d+\.\d+)\W*Om du ändrar eller avbokar efter (?<time>\d+:\d+), \d+\.\d+\.\d+ (?:boendets lokala tid )?\(.+?\),? kommer du att debiteras för \d+ natt \(inklusive skatt\)\W*Vi kan inte göra några återbetalningar om du inte dyker upp eller checkar ut tidigt#",
                "#^Om du ändrar eller avbokar efter (?<time>\d+:\d+), (?<date>\d+\.\d+\.\d+) \(.+\) kommer#",
            ],
            "membershipNumber"                          => "Ditt medlemsnummer på Hotels.com® Rewards är",
            "Hotels.com® Rewards reward* night applied" => "Hotels.com® Rewards bonusnatt inräknad",
            "Important notices"                         => "Viktiga notiser",
            "Price for room "                           => "Pris för rum ",
            //"Price per room per night" => "";
        ],
        'no' => [
            "confirmation number" => ["bekreftelsesnummer", "Hotels.com bekreftelsesnummer", "Hotels.com bestillingsnummer", "Bestillingsbekreftelse:", "Travellink bestillingsnummer"],
            "Dear "               => "Kjære ",
            "Hotel Details"       => ["Hotelldetaljer", "Informasjon om overnattingsstedet"],
            "cancelled"           => "Avbestillingen er gjennomført, så du behøver ikke gjøre noe mer",
            //            "StatusCancelled" => "",
            "The room is in the name of" => "Dette rommet er reservert under navnet",
            "Check in"                   => ["Innsjekking"],
            "Check out"                  => ["Utsjekking"],
            "Phone:"                     => ["Telefonnummer:"],
            "Your stay"                  => ["Ditt opphold"],
            "room"                       => ["rom"],
            "Cancellation policy"        => ["Avbestillingsregler"],
            "Room:"                      => ["rom", "Boenhet"],
            "Room @"                     => "Rom @",
            "Occupancy"                  => ["Gjester"],
            "adult"                      => ["voksen", "voksne"],
            "kids"                       => ["barn"],
            "Preferences"                => ["Preferanser", "Bekvämligheter"],
            "Taxes & fees"               => ["Skatter og avgifter", "Skatter"],
            "Amount paid"                => [
                "Totalpris betalt",
                "Totalprisen som må betales på hotellet",
                "Totalbeløpet som må betales på overnattingsstedet i lokal valuta",
                "Totalbeløp som vil bli belastet av overnattingsstedet",
                "Betalt beløp",
                "Totalbeløp som vil bli belastet av hotellet",
                "Beløp som må betales på overnattingsstedet",
                'Totalpris',
            ],
            //            "Payment schedule"           => "",
            "Room details"            => ["Fasiliteter"],
            "Room detailsH1"          => "Rominformasjon",
            "nonRefundableRegExp"     => "(?:Refunderes ikke|ikke refunderbar)",
            "Free cancellation until" => 'Gratis avbestilling frem til',
            "#Free cancellation...#"  => [
                "#^Gratis avbestilling frem til (?<date>\d+\.\d+\.\d+)\W*Dersom du endrer eller kansellerer bestillingen din etter (?<time>\d+:\d+), \d+\.\d+\.\d+ \(.+\),#",
                "#^Gratis avbestilling (?:frem|fram) til (?<time>\d+:\d+), (?<date>\d+\.\d+\.\d+) \(.+\)#",
                "#Hvis du endrer bestillingen din eller avbestiller etter (?<time>\d+:\d+) den (?<date>\d+\.\d+\.\d+) \(.+\),#",
                "#Fullt refunderbar fram til\s+(?<time>[\d\:]+)\,\s+(?<date>\d+\.\d+\.\d{4})\s*\(#",
            ],
            "membershipNumber"                          => "Ditt Hotels.com™ Rewards-medlemsnummer er",
            "Hotels.com® Rewards reward* night applied" => ["Hotels.com™ Rewards-bonusovernatting brukt", "Hotels.com™ Rewards bonusovernatting brukt"],
            "Important notices"                         => "Viktig merknad",
            "Price for room "                           => "Pris for rom ",
            "Price per room per night"                  => "Pris per rom per natt",
        ],
        'is' => [
            "confirmation number" => ["staðfestingarnúmer", "Hotels.com staðfestingarnúmer"],
            "Dear "               => "Bókunin er frágengin og greidd að fullu,",
            "Hotel Details"       => "Hótelupplýsingar",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Koma",
            "Check out"           => "Brottför",
            "Phone:"              => "Sími:",
            "Your stay"           => "Dvöl þín",
            "room"                => "herbergi",
            "Cancellation policy" => "Afbókunarreglur",
            //            "Room:" => "",
            //            "Room @" => "",
            "Occupancy" => "Gestir",
            "adult"     => "fullorðinn",
            //            "kids" => "",
            "Preferences"  => "Sérstakar óskir",
            "Taxes & fees" => "Skattar og gjöld",
            "Amount paid"  => [
                "Heildarupphæð greidd",
                "Upphæð greidd",
            ],
            //            "Payment schedule"           => "",
            //            "Room details" => "",
            //            "Room detailsH1" => "",
            "nonRefundableRegExp" => "(?:Sértilboð sem fæst ekki endurgreitt)",
            //            "Free cancellation until" => '',
            //            "#Free cancellation...#" => "",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Mikilvægar tilkynningar",
            //"Price for room " => "",
            "Price per room per night" => "herbergi",
        ],
        'zh' => [
            "confirmation number" => ["預訂確認編號", "Hotels.com 確認編號", "Hotels.com 確認號碼", "Hotels.com 好订网 确认编号"],
            "Dear "               => "親愛的 ",
            "Hotel Details"       => ["飯店詳細資料", "酒店詳細資料", "住宿詳細資料", "酒店详情", "住宿詳情"],
            "cancelled"           => ["我們已經依要求取消了您的預訂", "我們已按要求取消了你的預訂"],
            //            "StatusCancelled" => "",
            "The room is in the name of" => ["此住宿單位以", "此客房以"],
            "Check in"                   => "入住",
            "Check out"                  => "退房",
            "Phone:"                     => ["電話：", "电话："],
            "Your stay"                  => ["您的住宿", "你的住宿", "预订的住宿"],
            "room"                       => ["間客房", "间房", "個單位"],
            "Cancellation policy"        => ["取消規定", "取消政策", "取消须知"],
            "Room:"                      => ["客房", "房間", "特殊要求"],
            "Room @"                     => "NOTTRANSLATED",
            "adult"                      => "位成人",
            "kids"                       => "位兒童",
            "Preferences"                => "特別要求",
            "Taxes & fees"               => ["稅金和服務費", "稅項及其他費用"],
            "Amount paid"                => ["在飯店以當地貨幣付款的總金額", "已支付總金額", "已付總金額", "已付款额", "将付给住宿的金额", "总价格", '總價'],
            //            "Payment schedule"           => "",
            "Room details"               => ["包括", "設施/服務", "客房详细信息", "设施"],
            "Room detailsH1"             => "客房資訊",
            "nonRefundableRegExp"        => ["不可退費", "特價飯店，不得退款.", "不可退款预订. ", "此特别折扣房价不可退款"],
            "Free cancellation until"    => '前可免費取消',
            "#Free cancellation...#"     => [
                "#\d+\-\d+\-\d+ 前可免費取消\W*如果您在 (?<date>\d+\-\d+\-\d+)(?<pm>下午) (?<time>\d+:\d+) \(.+\) 後變更或取消訂房，必須支付 \d+ 晚的費用 \(含稅\)\W*未入住或提早退房，將不會獲得退款#u",
                "#\d+\/\d+\/\d+ 前可免費取消\W*如你於 (?<date>\d+\/\d+\/\d+)(?<pm>下午)? (?<time>\d+:\d+) \(.+\) 後更改或取消預訂，將被收取 \d+ 晚費用 \(連稅\)\W*未入住或提早退房，將不獲退款#u",
                "#如果您在 (?<date>\d+\-\d+\-\d+) (?<time>\d+:\d+) .+之后更改或取消预订#u",
                "#如你於住宿當地時間 (?<date>\d+\/\d+\/\d+) (?<time>\d+:\d+) \(.+\) 後更改或取消預訂，你將需支付 \d{1,3}% 費用#",
                "#免费取消 截至 (?<date>\d+.\d+.\d+) (?<time>下午\d+:\d+)\s*\(.+\)#u",
            ],
            "Occupancy"                                 => ["旅客人數", "住客数"],
            "membershipNumber"                          => "您的 Hotels.com™ Rewards 會員編號是",
            "Hotels.com® Rewards reward* night applied" => ["使用 Hotels.com™ Rewards 獎勵*住宿", "使用 Hotels.com™ Rewards 免費晚數"],
            "Important notices"                         => "重要通知",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'fi' => [
            "confirmation number" => ["Hotels.comin vahvistusnumero", 'Varausvahvistus:'],
            "Dear "               => "Hei ",
            "Hotel Details"       => ["Hotellitiedot", "Majoitustilan tiedot", "Majoitusliikkeen tiedot"],
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Tulopäivä",
            "Check out"           => "Lähtöpäivä",
            "Phone:"              => "Puh:",
            "Your stay"           => "Yöpymisen tiedot",
            "room"                => ["huone", "yksikkö"],
            "Cancellation policy" => "Peruutusehdot",
            "Room:"               => ["Huone"],
            "Room @"              => "NOTTRANSLATED",
            "adult"               => "aikuista",
            "kids"                => "lasta",
            "Preferences"         => "Toiveet",
            "Taxes & fees"        => ["Verot & maksut", "Verot"],
            "Amount paid"         => [
                "Hotellilla paikallisssa valuuttassa maksettava loppusumma",
                "Maksettu summa",
                "Hotellilla paikallisessa valuutassa maksettava loppusumma",
                "Majoitusliikkeen veloittama loppusumma",
                "Kokonaishinta",
            ],
            "Payment schedule"           => "Maksuaikataulu",
            "Room details"               => ["Hintaan sisältyy", "Tilat"],
            "Room detailsH1"             => "Yksikön tiedot",
            "nonRefundableRegExp"        => "Ei-hyvitettävä",
            "Free cancellation until"    => 'Täysin hyvitettävä',
            "#Free cancellation...#"     => [
                "#^Ilmainen peruutusoikeus (?<date>[\d\/.]+(?:\s*\d+:\d+)?)\s*\(.+\) asti$#m",
                "#^\s*Täysin hyvitettävä\.? (\d{1,2}[.:]\d{2}),\s*(?<date>[\d\/.]+\d{4})\s*\([A-Z]{2,4}#m",
            ],
            "Occupancy"              => "Asiakkaat",
            "membershipNumber"       => "Hotels.com® Rewards-ohjelman jäsennumerosi on",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Tärkeitä huomioita",
            "Price for room "             => "Hinta huoneelle ",
            //"Price per room per night" => "";
        ],
        'ru' => [
            "confirmation number" => ["номер подтверждения Hotels.com", "Подтверждение бронирования №"],
            //			"Dear " => "",
            "Occupancy"     => "Гости",
            "Hotel Details" => ["Сведения об отеле", 'Информация об отеле', 'Сведения о жилье'],
            "cancelled"     => ["мы отменили последний номер в бронировании", "мы отменили бронирование"],
            //            "StatusCancelled" => "",
            "The room is in the name of" => "Этот номер забронирован на имя",
            "Check in"                   => ["Прибытие", 'Заезд'],
            "Check out"                  => ["Отъезд", 'Выезд'],
            "Phone:"                     => "Телефон:",
            "Your stay"                  => "Ваше проживание",
            "room"                       => ["номер", "помещение"],
            "Cancellation policy"        => "Правила отмены бронирования",
            "Room:"                      => ["Номер"],
            "Room @"                     => "Номер @",
            "adult"                      => "взросл",
            "kids"                       => "NOTTRANSLATED",
            "Preferences"                => "Предпочтения",
            "Taxes & fees"               => ["Налоги и сборы", "Налоги"],
            "Amount paid"                => [
                "Общая сумма",
                "Общая сумма к оплате в отеле",
                'Сумма к оплате в отеле, в местной валюте',
                'Выплаченная сумма',
                'К оплате в объекте размещения',
                'Итого к оплате в отеле',
            ],
            //            "Payment schedule"           => "",
            "Room details"            => "В том числе",
            "Room detailsH1"          => "Сведения об объекте",
            "nonRefundableRegExp"     => "Невозмещаемый тариф",
            "Free cancellation until" => 'Бесплатная отмена бронирования до',
            "#Free cancellation...#"  => "#^Бесплатная отмена бронирования до (?<date>[\d\/]+(?:\s*\d+:\d+)?)\s*\(.+\)$#m",
            "membershipNumber"        => "Ваш номер участника Hotels.com™ Rewards:",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            "Price for room "          => "Цена за номер ",
            "Price per room per night" => "Суточная цена за номер",
        ],
        'es' => [
            "confirmation number" => [
                "Número de confirmación de Hoteles.com",
                "Número de confirmación de Hotels.com",
                "N.º de confirmación de la reserva",
                "Confirmación de la reservación núm.",
                "Confirmación #",
                "Número de confirmación de eDreams Prime",
            ],
            "Dear "                      => "Hola, ",
            "Hotel Details"              => ["Detalles del hotel", "Detalles del establecimiento", "Detalles del alojamiento", "Información del hospedaje"],
            "cancelled"                  => ["hemos cancelado tu reserva", "Te confirmamos que ya cancelamos tu reservación", "cancelamos tu reservación"],
            "StatusCancelled"            => "hemos (cancelado) tu reserva",
            "The room is in the name of" => ["La habitación está a nombre de", "La unidad está a nombre de", "Habitación a nombre de"],
            "Check in"                   => ["Llegada", "Check-in"],
            "Check out"                  => ["Salida", "Check-out"],
            "Phone:"                     => "Teléfono:",
            "Your stay"                  => ["Tu estancia", "Tu estadía", "Tu reserva"],
            "room"                       => ["habitación", "unidad"],
            "Cancellation policy"        => "Política de cancelación",
            "Room:"                      => ["Habitación"],
            "Room @"                     => "NOTTRANSLATED",
            "adult"                      => ["adult", "adultos"],
            "kids"                       => "niño",
            "Preferences"                => "Preferencias",
            "Taxes & fees"               => ["Impuestos y tasas", "Impuestos y cargos", "Impuestos", "Cargos e impuestos"],
            "Amount paid"                => [
                "Total pagado", "Total que cobrará el establecimiento", "Monto pagado", "Importe pagado", "Precio total", "Importe total que cargará el alojamiento",
                "Monto a pagar en el establecimiento", ],
            "Payment schedule"           => "Plan de pagos",
            "Room details"               => ["Instalaciones", "Incluido", "Servicios e instalaciones"],
            "Room detailsH1"             => ["Detalles de la habitación", "Detalles de la habitación", "Detalles de la unidad"],
            "nonRefundableRegExp"        => "Reservación no reembolsable\.",
            'Free cancellation until'    => ['Cancelación gratuita hasta el', 'Cancelación sin costo hasta el', 'Cancelación gratis hasta el'],
            "#Free cancellation...#"     => [
                "#Si cambias o cancelas la reserva después del (?<date>\d+[\/\-]\d+[\/\-]\d+) a las (?<time>\d+:\d+\s*(?:[AP]M)?)\s*\(.+\)\s*, deberás pagar \d+ noche \(tasas incluidas\)#i",
                "#Si cambias o cancelas la reserva después de las (?<time>\d+:\d+\s*(?:[AP]M)?) del (?<date>\d+[\/\-]\d+[\/\-]\d+), [^\d\.]*\s*\(.+\)\s*, tendrás que pagar una penalización del \d+ %#i",
                "#Si cambias o cancelas tu reservación después de las (?<time>\d+:\d+\s*(?:[AP]M)?) de (?<date>\d+[\/\-]\d+[\/\-]\d+), [^\d\.]*\s*\(.+\)\s*, se te hará un cargo por el \d+%#i",
                "#^Cancelación gratis hasta e?l?\s*(?<time>\d+:\d+\s*a?p?\.?\s*m\.)\,\s*(?<date>[\d\/]+)\s*#ui",
                "#^Cancelación gratuita\. hasta la fecha\:\s*(?<time>\d+:\d+)\,\s*(?<date>[\d\/]+)\s*#ui",
                "/^Cancelación gratuita hasta el (?<date>\d+\/\d+\/\d+)\. Si cambias o cancelas la reserva después de las (?<time>\d{1,2}:\d{2}) del \\1\W/ui",
                "/^Cancelación gratuita hasta el (?<date>\d+\/\d+\/\d+)\. Si cambias o cancelas la reserva después del \\1 \([^\)]+\) a las (?<time>\d{1,2}:\d{2})\W/ui",
                "/^Cancelación gratuita hasta el (?<time>\d{1,2}:\d{2}), (?<date>\d+\/\d+\/\d+) \([^\)]+\)\./ui",
                "/^Totalmente reembolsable hasta (?<time>\d{1,2}:\d{2}(?:\s*[ap]\.? *m\.?\s*)?), (?<date>\d+\/\d+\/\d+) \([^\)]+\)\./ui",
            ],
            "membershipNumber"                          => ["Tu Hoteles.com® Rewards número de socio es", "Tu número de socio Hotels.com® Rewards es"],
            "Occupancy"                                 => "Huéspedes",
            "Hotels.com® Rewards reward* night applied" => "Se ha utilizado una noche extra* de Hoteles.com® Rewards",
            "Important notices"                         => "Avisos importantes",
            "Price for room "                           => "Precio habitación ",
            //"Price per room per night" => "";
        ],
        'da' => [
            "confirmation number"        => ["Hotels.com-bekræftelsesnummer", "-bekræftelsesnummer", "Reservationsbekræftelse:"],
            "Dear "                      => "Hej ",
            "Hotel Details"              => ["Hoteloplysninger", "Oplysninger om overnatningsstedet"],
            "cancelled"                  => "afbestilt",
            "StatusCancelled"            => "anmodning har vi (afbestilt) din reservation",
            "The room is in the name of" => "Denne enhed er reserveret under navnet",
            "Check in"                   => "Indtjekning",
            "Check out"                  => "Udtjekning",
            "Phone:"                     => "Telefon:",
            "Your stay"                  => "Dit ophold",
            "room"                       => "værelse",
            "Cancellation policy"        => "Afbestillingspolitik",
            "Room:"                      => ["Værelse"],
            "Room @"                     => "NOTTRANSLATED",
            "adult"                      => ["voksne", "voksen"],
            "kids"                       => "børn",
            "Preferences"                => "Ønsker",
            "Taxes & fees"               => "Skatter og afgifter",
            "Amount paid"                => ["I alt betalt", 'Beløb, som skal betales på hotellet i den lokale valuta',
                'Samlet pris', ],
            //            "Payment schedule"           => "",
            "Room details"               => "Faciliteter",
            "Room detailsH1"             => "Værelsesoplysninger",
            "nonRefundableRegExp"        => "Ikke-refunderbar",
            //            "Free cancellation until" => '',
            "#Free cancellation...#"                    => "#Hvis du ændrer eller afbestiller denne reservation efter den (?<date>[\d\.]+) kl\. (?<time>\d+:\d+)\s*GMT.*\(.+\)#m",
            "membershipNumber"                          => "Dit Hotels.com® Rewards-medlemsnummer er",
            "Occupancy"                                 => "Gæster",
            "Hotels.com® Rewards reward* night applied" => ["Hotels.com® Rewards-bonusnat* anvendt", "Hotels.com® Rewards-bonusnat anvendt"],
            "Important notices"                         => "Vigtige bemærkninger",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'it' => [
            "confirmation number"        => ["Numero di conferma Hotels.com", "Numero di conferma", "N° di conferma della prenotazione"],
            "Dear "                      => "Gentile ",
            "Hotel Details"              => ["Dettagli hotel", "Dettagli della struttura"],
            "cancelled"                  => "cancellato",
            "StatusCancelled"            => "abbiamo (cancellato) la prenotazione",
            "The room is in the name of" => "Prenotazione a nome di",
            "Check in"                   => ["Arrivo", "Check-in"],
            "Check out"                  => ["Partenza", "Check-out"],
            "Phone:"                     => "Telefono:",
            "Your stay"                  => "Il tuo soggiorno",
            "room"                       => ["camera", "unità"],
            "Cancellation policy"        => "Condizioni di cancellazione",
            "Room:"                      => ["Camera", "Unità"],
            "Room @"                     => "Camera @",
            "adult"                      => "adult",
            "kids"                       => "bambin",
            "Preferences"                => "Preferenze",
            "Taxes & fees"               => "Tasse e oneri",
            "Amount paid"                => [
                "Importo totale da pagare all’hotel",
                'Importo totale pagato',
                'Importo totale da pagare alla struttura',
                'Prezzo totale',
            ],
            "Payment schedule"           => "Programma di pagamento",
            "Room details"               => ["Servizi", "Dettagli dell’unità"],
            "Room detailsH1"             => "Informazioni sulla camera",
            "nonRefundableRegExp"        => "(?:Non rimborsabile|Prenotazione non rimborsabile)",
            //                        "Free cancellation until" => '',
            "#Free cancellation...#" => [
                '#Cancellazione gratuita fino alla data (?<date>\d+\/\d+\/\d+)\W*Se modifichi o cancelli la tua prenotazione dopo le ore (?<time>\d+[.:]\d+) del giorno#',
                //  Completamente rimborsabile fino alla data 17:00, 20/08/2022 (GMT+02:00)
                '#^\s*Completamente rimborsabile fino alla data (?<time>\d+:\d+)\s*,\s*(?<date>\d+\/\d+\/\d+)\s*\(\s*[A-Z]{2,4}#u',
            ],
            "Occupancy"                                 => ["Ospiti"],
            "membershipNumber"                          => "Il tuo numero di iscrizione a Hotels.com® Rewards è",
            "Hotels.com® Rewards reward* night applied" => "Notte Hotels.com® Rewards gratis utilizzata",
            "Important notices"                         => "Comunicazioni importanti",
            //"Price for room " => "",
            "Price per room per night" => "Prezzo per camera a notte",
        ],
        'ko' => [
            "confirmation number" => ["Hotels.com 확인 번호", "예약 확인 번호:"],
            //			"Dear " => "",
            "Hotel Details" => ["호텔 세부 정보", "숙박 시설 세부 정보"],
            "cancelled"     => "요청하신 대로 예약을 취소해 드렸습니다",
            //            "StatusCancelled" => "",
            "The room is in the name of" => "님 이름으로 예약된 객실입니다",
            "Check in"                   => ["체크인", "Check-in"],
            "Check out"                  => ["체크아웃", "Check-out"],
            "Phone:"                     => "전화:",
            "Your stay"                  => ["숙박 기간 및 객실 수", "숙박"],
            "room"                       => "객실",
            "Cancellation policy"        => "예약취소 정책",
            "Room:"                      => ["객실", "유닛"],
            //            "Room @" => "",
            "adult"               => "명의 성인",
            "kids"                => "명의 어린이/청소년",
            "Preferences"         => "선호 사항",
            "Taxes & fees"        => "세금 및 수수료",
            "Amount paid"         => ["지불한 금액", "지불된 총 금액", "숙박 시설에서 현지 통화로 지불하실 금액", "결제된 총 금액"],
            //            "Payment schedule"           => "",
            "Room details"        => ["편의 시설"],
            "Room detailsH1"      => "객실 세부 정보",
            "nonRefundableRegExp" => "특별 요금\(환불 불가\)\. 이 특별 할인 요금은 환불되지 않습니다\.",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => "#(?<date>[\d\/]+\s*\w+\s*(?:\s*\d+:\d+)?)\s*(\(.*\))?\s*까지 무료 취소#u",
            //?need more extended sentence
            "membershipNumber" => "고객님의 Hotels.com™ 호텔스닷컴 리워드 멤버십 번호는 ",
            "Occupancy"        => "인원 수",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "중요 공지 사항",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'tr' => [
            "confirmation number" => ["Hotels.com onay numarası"],
            "Dear "               => "Merhaba ",
            "Hotel Details"       => "Otel Bilgileri",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Giriş",
            "Check out"           => "Çıkış",
            "Phone:"              => "Telefon:",
            "Your stay"           => "Konaklama bilgileri",
            "room"                => "oda",
            "Cancellation policy" => "İptal politikası",
            "Room:"               => ["Oda"],
            "Room @"              => "NOTTRANSLATED",
            "adult"               => "yetişkin",
            "kids"                => "NOTTRANSLATED",
            "Preferences"         => "Tercihler",
            "Taxes & fees"        => "Vergi ve ek ücretler",
            "Amount paid"         => ["Ödenen toplam tutar", "Otelde yerel para"],
            //            "Payment schedule"           => "",
            "Room details"        => "Otel Özellikleri",
            //            "Room detailsH1" => "",
            //			"nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => [
                "#(?<date>[\d\/]+(?:\s*\d+:\d+)?)\s*(\(.*\))?\s*tarihine kadar ücretsiz iptal#u",
                "#^(?<date>[\d\/]+(?:\s*\d+:\d+)?|\d+:\d+, [\d\/]+)\s*(\(.*\))?\s*(?:tarihine kadar ücretsiz iptal|tarihinden sonra rezervasyonunuzu değiştirir veya iptal)#u",
            ], //?need more extended sentence
            "Occupancy" => ["Misafir Sayısı"],
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'ja' => [
            "confirmation number" => ["Hotels.com 確認番号", "PEACH ROOMS 確認番号", "予約確認番号 :"],
            //			"Dear " => "",
            "Hotel Details" => ["ホテルの詳細", "宿泊施設の詳細"],
            "cancelled"     => "ご予約がキャンセルされました。",
            //            "StatusCancelled" => "",
            "The room is in the name of" => "様のお名前で承っております",
            "Check in"                   => "チェックイン",
            "Check out"                  => "チェックアウト",
            "Phone:"                     => "電話番号:",
            "Your stay"                  => "宿泊",
            "room"                       => "部屋",
            "Cancellation policy"        => ["キャンセル ポリシー", "キャンセルポリシー"],
            "Room:"                      => ["部屋"],
            "Room @"                     => "NOTTRANSLATED",
            "Occupancy"                  => "宿泊者",
            "adult"                      => "大人",
            "kids"                       => "お子様",
            "Preferences"                => "ご希望のベッドタイプ",
            "Taxes & fees"               => ["税およびサービス料", "税金"],
            "Amount paid"                => ["支払い済み合計料金", "宿泊施設でのお支払い金額", '宿泊施設でのお支払い金額合計',
                '合計', ],
            "Payment schedule"           => "お支払いスケジュール",
            "Room details"               => "設備",
            "Room detailsH1"             => ["部屋の詳細", "ユニットの詳細"],
            //			"nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => [
                "#(?:^|[.][ ]+)ご予約を (?<date>\d{4}\/\d+\/\d+) (?<time>\d+:\d+) \([^)(]+\) 以降に変更、キャンセルした場合には、\d+ % のキャンセル料が請求されます#m",
                "#(?:^|[.][ ]+)ご予約を (?<date>\d{4}\/\d+\/\d+) (?<time>\d+:\d+) \([^)(]+\) 以降に変更、キャンセルした場合には、\d+ 泊 \(税込\) 分の(?:部屋|客室)料金が請求されます#mu",
                "#^\s*全額返金可\.? (?<date>\d{4}\/\d+\/\d+)、(?<time>\d+:\d+) \([^)(]+\) まで\.#mu",
            ],
            "membershipNumber"                          => ["お客様のHotels.com™ Rewards会員番号は", "お客様のHotels.com™ リワード会員番号は", 'Hotels.com 確認番号'],
            "Hotels.com® Rewards reward* night applied" => ["Hotels.com™ Rewards無料*宿泊特典を適用", "Hotels.com™ リワードのボーナスステイを適用"],
            "Important notices"                         => "重要なお知らせ",
            //"Price for room " => "",
            //"Price per room per night" => "";
        ],
        'hr' => [
            "confirmation number" => ["Hotels.com broj potvrde"],
            "Dear "               => "Pozdrav,",
            "Hotel Details"       => "Detalji hotela",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Dolazak",
            "Check out"           => "Odlazak",
            "Phone:"              => "Telefon:",
            "Your stay"           => "Vaš boravak",
            "room"                => "soba",
            "Cancellation policy" => "Pravila otkazivanja:",
            "Room:"               => ["Soba"],
            "Room @"              => "NOTTRANSLATED",
            "adult"               => "odrasl",
            "kids"                => "NOTTRANSLATED",
            "Preferences"         => "Željene karakteristike:",
            "Taxes & fees"        => "Porezi i naknade",
            "Amount paid"         => ["Ukupni plaćeni iznos"],
            //            "Payment schedule"           => "",
            //			"Room details" => "",
            //            "Room detailsH1" => "",
            "nonRefundableRegExp" => "Ako odlučite promijeniti ili otkazati ovu rezervaciju\W*nećete moći ostvariti povrat bilo kojeg uplaćenog iznosa",
            //            "Free cancellation until" => '',
            //			"#Free cancellation...#" => "#(?<date>[\d\/]+(?:\s*\d+:\d+)?)#",
            //            "membershipNumber" => "",
            //            "Occupancy" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Važne obavijesti",
            'Price for room '             => 'Cijena za sobu broj ',
            //"Price per room per night" => "";
        ],
        'hu' => [
            "confirmation number" => ["Hotels.com foglalási szám"],
            "Dear "               => "Gyorgy,",
            "Hotel Details"       => ["A szállás adatai", "A szálláshely részletei"],
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Érkezés dátuma",
            "Check out"           => "Távozás dátuma",
            "Phone:"              => "Telefon:",
            "Your stay"           => "Tartózkodás",
            "room"                => "szoba",
            "Cancellation policy" => "Visszamondási feltételek",
            "Room:"               => ["Szoba"],
            //            "Room @"=>"NOTTRANSLATED",
            "adult"        => "felnőtt",
            "kids"         => "gyermek",
            "Preferences"  => "Egyéni igények",
            "Taxes & fees" => "Adók és egyéb díjak",
            "Amount paid"  => ["A hotelben fizetendő összeg a helyi pénznemben", "Kifizetett összeg"],
            //            "Payment schedule"           => "",
            //			"Room details" => "",
            //            "Room detailsH1" => "",
            //            "nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => "#(?<date>[\d\/]+(?:\s*\d+:\d+)?)#",
            "Occupancy"              => ["Vendégek"],
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'he' => [
            "confirmation number" => ["מספר אישור של Hotels.com"],
            "Dear "               => "שלום,",
            "Hotel Details"       => ["פרטי מלון", "פרטי הנכס"],
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "הגעה",
            "Check out"           => "עזיבה",
            "Phone:"              => "טלפון:",
            "Your stay"           => "השהות שלכם",
            "room"                => "חדר",
            "Cancellation policy" => "מדיניות ביטול",
            "Room:"               => ["חדר"],
            "Room @"              => "NOTTRANSLATED",
            "adult"               => "מבוגרים",
            "kids"                => "NOTTRANSLATED",
            "Preferences"         => "העדפות",
            "Taxes & fees"        => "מיסים ועמלות",
            "Amount paid"         => ["סכום כולל ששולם", "סכום כולל שיגבה על ידי המלון", "סכום לתשלום במלון, במטבע", "סכום ששולם"],
            //            "Payment schedule"           => "",
            "Room details"        => "שירותים",
            "Room detailsH1"      => "פרטי החדר",
            //			"nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => ["#ביטול חינם עד \d+\/\d+\/\d+\W* שינוי או ביטול של הזמנה זו אחרי (?<time>\d+:\d+)\s*,\s*(?<date>\d+\/\d+\/\d+) \(.+ Time\) יגרור חיוב חשבונכם בעמלה של 100%#",
                "#ביטול חינם עד\s*(?<time>\d+\:\d+)\,\s*(?<date>\d+\/\d+\/\d+)#", ],
            "membershipNumber"                          => "מספר החברות שלכם בתכנית Hotels.com™ Rewards הוא",
            "Occupancy"                                 => "אורחים",
            "Hotels.com® Rewards reward* night applied" => "לילה Hotels.com™ Rewards במתנה הוחל",
            "Important notices"                         => "מידע חשוב",
            "Price for room "                           => "מחיר עבור חדר ",
            //"Price per room per night" => ""
        ],
        'pl' => [
            "confirmation number" => ["Numer potwierdzenia rezerwacji Hotels.com"],
            //            "Dear " => "",
            "Hotel Details" => ["Szczegóły dotyczące hotelu", "Szczegółowe informacje o obiekcie", "Szczegóły hotelu", "Szczegóły obiektu"],
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Data przyjazdu",
            "Check out"           => ["Data wyjazdu", "Data wymeldowania"],
            "Phone:"              => "Telefon:",
            "Your stay"           => "Twój pobyt",
            "room"                => ["pokój", "jednostki", 'jednostka'],
            "Cancellation policy" => "Zasady anulowania rezerwacji",
            "Room:"               => ["Pokój"],
            "Room @"              => "Jednostka @",
            "adult"               => "osoba dorosła",
            //            "kids"=>"",
            "Preferences"  => "Preferencje",
            "Taxes & fees" => ["Podatki i opłaty", "Podatki"],
            "Amount paid"  => [
                "Całkowita kwota do zapłaty hotelowi",
                "Kwota zapłacona",
                "Całkowita kwota do zapłaty w obiekcie",
                "Cena całkowita",
            ],
            "Payment schedule"           => "Terminarz płatności",
            "Room details"               => "Udogodnienia",
            "Room detailsH1"             => ["Szczegóły jednostki", "Szczegóły dotyczące pokoju"],
            "nonRefundableRegExp"        => "Rezerwacja bez możliwości zwrotu kosztów",
            "Free cancellation until"    => 'Pełny zwrot kosztów do',
            "#Free cancellation...#"     => [
                "/Możliwość bezpłatnego anulowania do (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})(?:. Jeśli rezerwacja zostanie anulowana lub zmieniona po (?<time>\d+:\d+), \\1)?/",
                "/Pełny zwrot kosztów do\s*(?<time>\d+:\d+\s*(?:[AP]M)?),\s*(?<date>\d+[\/\-]\d+[\/\-]\d+)\s*\(/",
            ],
            "Occupancy"              => ["Goście"],
            "membershipNumber"       => "Twój numer uczestnika w programie Hotels.com® Rewards to",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Ważne informacje",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'el' => [
            "confirmation number" => ["Αριθμός επιβεβαίωσης"],
            //            "Dear " => "",
            "Hotel Details" => "Στοιχεία ξενοδοχείου",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Άφιξη",
            "Check out"           => "Αναχώρηση",
            "Phone:"              => "Τηλέφωνο:",
            "Your stay"           => "Η διαμονή σας",
            "room"                => ["δωμάτιο"],
            "Cancellation policy" => "Πολιτική ακύρωσης",
            "Room:"               => ["Δωμάτιο "],
            //            "Room @"=>"",
            "Occupancy" => "Πελάτες",
            "adult"     => "ενήλικες",
            //            "kids"=>"",
            //            "Preferences"=>"",
            "Taxes & fees" => "Φόροι και τέλη",
            "Amount paid"  => ["Πληρωθέν ποσό", "Συνολικό ποσό που πληρώθηκε"],
            //            "Payment schedule"           => "",
            "Room details" => "Προτιμήσεις",
            //            "Room detailsH1" => "",
            "nonRefundableRegExp" => "Ειδική τιμή χωρίς επιστροφή χρημάτων",
            //            "Free cancellation until" => '',
            //            "#Free cancellation...#" => "/Możliwość bezpłatnego anulowania do (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})(?:. Jeśli rezerwacja zostanie anulowana lub zmieniona po (?<time>\d+:\d+), \\1)?/",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'sk' => [
            "confirmation number" => ["číslo potvrdenia"],
            //            "Dear " => "",
            "Hotel Details" => "Podrobnosti o hoteli",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Príchod",
            "Check out"           => "Odchod",
            "Phone:"              => "Telefón:",
            "Your stay"           => "Váš pobyt",
            "room"                => ["izba"],
            "Cancellation policy" => "Storno podmienky",
            "Room:"               => ["Váš pobyt"],
            //            "Room @"=>"",
            "Occupancy" => "Hostia",
            "adult"     => "dospelý",
            //            "kids"=>"",
            //            "Preferences"=>"",
            "Taxes & fees" => "Dane a poplatky",
            "Amount paid"  => ["Celková čiastka, ktorú vám naúčtuje hotel"],
            //            "Payment schedule"           => "",
            "Room details" => "Podrobné údaje o izbe",
            //            "Room detailsH1" => "",
            //  "nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => "/Bezplatné storno až do dňa\s*(?<date>\d+[\/\-]\d+[\/\-]\d+)\W*Ak zmeníte alebo zrušíte vašu rezerváciu po\s*(?<time>\d+:\d+\s*(?:[AP]M)?)/",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'th' => [
            "confirmation number" => ["หมายเลขยืนยัน Hotels.com"],
            //            "Dear " => "",
            "Hotel Details" => "รายละเอียดโรงแรม",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "เช็คอิน",
            "Check out"           => "เช็คเอาท์",
            "Phone:"              => "โทรศัพท์:",
            "Your stay"           => "ระยะเวลาการเข้าพัก",
            "room"                => ["ห้อง"],
            "Cancellation policy" => "นโยบายการยกเลิก",
            "Room:"               => ["ผู้เข้าพัก"],
            //            "Room @"=>"",
            "Occupancy" => "ผู้เข้าพัก",
            "adult"     => "ผู้ใหญ่",
            //            "kids"=>"",
            //            "Preferences"=>"",
            "Taxes & fees" => "ภาษีและค่าธรรมเนียม",
            "Amount paid"  => ["จำนวนเงินที่ต้องชำระทั้งหมด", "จำนวนเงินที่ต้องชำระทั้งหมด"],
            //            "Payment schedule"           => "",
            "Room details" => "รายละเอียดห้อง",
            //            "Room detailsH1" => "",
            // "nonRefundableRegExp" => "",
            //            "Free cancellation until" => '',
            "#Free cancellation...#" => "/ยกเลิกฟรีจนถึง\s*(?<date>\d+[\/\-]\d+[\/\-]\d+)\W*. หากคุณเปลี่ยนแปลงหรือยกเลิกการจองหลัง\s*(?<time>\d+:\d+\s*(?:[AP]M)?)/",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            //            "Important notices"           => "",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'id' => [
            "confirmation number" => ["Nomor konfirmasi Hotels.com"],
            "Dear "               => "Halo ",
            "Hotel Details"       => "Rincian Hotel",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Check-in",
            "Check out"           => "Check-out",
            "Phone:"              => "Telepon:",
            "Your stay"           => "Masa inap Anda",
            "room"                => ["kamar"],
            "Cancellation policy" => "Kebijakan pembatalan",
            "Room:"               => ["Kamar"],
            "Room @"              => "Kamar @",
            "Occupancy"           => "Tamu",
            "adult"               => "dewasa",
            //            "kids"=>"",
            //            "Preferences" => "",
            "Taxes & fees" => "Pajak & biaya",
            "Amount paid"  => ["Harga total", "จำนวนเงินที่ต้องชำระทั้งหมด"],
            //            "Payment schedule"           => "",
            "Room details"   => "Fasilitas",
            "Room detailsH1" => "Rincian kamar",
            // "nonRefundableRegExp" => "",
            "Free cancellation until" => 'Refundable penuh',
            "#Free cancellation...#"  => "/Refundable penuh hingga\s*(?<time>\d{1,2}\.\d{2}), (?<date>\d+[\/\-]\d+[\/\-]\d+)\s*\([A-Z]{2,4}\s*/",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Pemberitahuan penting",
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
        'cs' => [
            "confirmation number" => ["Číslo potvrzení Hotels.com"],
            //"Dear "               => "",
            "Hotel Details"       => "Údaje o hotelu",
            //            "cancelled" => "",
            //            "StatusCancelled" => "",
            //            "The room is in the name of" => "",
            "Check in"            => "Registrace",
            "Check out"           => "Odhlášení",
            "Phone:"              => "Tel.:",
            "Your stay"           => "Pobyt",
            "room"                => ["pokoj"],
            "Cancellation policy" => "Storno podmínky",
            //"Room:"               => [""],
            //"Room @"              => "",
            "Occupancy"           => "Hosté",
            "adult"               => "dospělý",
            //            "kids"=>"",
            //            "Preferences" => "",
            "Taxes & fees"               => "Daně a poplatky",
            "Amount paid"                => ["Celková cena"],
            "Payment schedule"           => "Údaje o platbě",
            "Room details"               => "Vybavení",
            //"Room detailsH1" => "",
            "nonRefundableRegExp" => "Nevratná rezervace",
            //"Free cancellation until" => 'Refundable penuh',
            //"#Free cancellation...#"  => "/Refundable penuh hingga\s*(?<time>\d{1,2}\.\d{2}), (?<date>\d+[\/\-]\d+[\/\-]\d+)\s*\([A-Z]{2,4}\s*/",
            //            "membershipNumber" => "",
            //            "Hotels.com® Rewards reward* night applied" => "",
            "Important notices"           => "Pemberitahuan penting",
            "RateText"                    => ['Dvoulůžkový pokoj'],
            //"Price for room " => "",
            //"Price per room per night" => ""
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            $class = explode('\\', __CLASS__);
            $email->setType(end($class));
            $this->logger->debug("lang not defined");

            return $email;
        }

        $this->parseHtml($email);

        if (empty($this->providerCode)) {
            $body = $parser->getHTMLBody();

            foreach ($this->detectCompany as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($body, $name) !== false) {
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $code => $values) {
            foreach ($values['from'] as $emailFrom) {
                if (stripos($from, $emailFrom) !== false) {
//                    $this->providerCode = $code;
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectHeaders as $values) {
            $foundFrom = false;

            foreach ($values['from'] as $emailFrom) {
                if (stripos($headers['from'], $emailFrom) !== false) {
//                    $this->providerCode = $code;
                    $foundFrom = true;

                    break;
                }
            }

            if (!$foundFrom && strpos($headers['subject'], 'Hotels.com') === false) {
                continue;
            }

            foreach ($values['subj'] as $emailSubj) {
                if (strpos($headers['subject'], $emailSubj) !== false) {
//                    $this->providerCode = $code;
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detectCompany as $code => $names) {
            foreach ($names as $name) {
                if (stripos($body, $name) !== false && $this->assignLang() == true) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    private function parseHtml(Email $email)
    {
        // for two reservation in one email
        $xpath = "//text()[" . $this->contains($this->t("confirmation number")) . "]/ancestor::*[(" . $this->contains($this->t("Hotel Details")) . " or " . $this->contains($this->t("confirmation.mail.location.details.unhotelling")) . ")][1]";
//        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query('/');
        }

        foreach ($nodes as $ni => $gRoot) {
            // Travel agency
            $email->obtainTravelAgency();
            $conf = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("confirmation number")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $gRoot, true, "#^\s*([\dA-Z]{5,})\s*$#u");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("confirmation number")) . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]",
                    $gRoot, true, "#^\s*([\dA-Z]{5,})\s*$#u");
            }

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("confirmation number")) . " and not(.//td)]/following-sibling::td[1]",
                    $gRoot, true, "#^\s*([\dA-Z]{5,})\s*$#u");
            }

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confirmation number'))}][1]",
                    $gRoot, true, '/(\d+)/');
            }

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode(".//*[{$this->contains($this->t('confirmation number'))}]/span[1]",
                    $gRoot, true, '/(\d+)/');
            }

            if (!in_array($conf, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
                $email->ota()
                    ->confirmation($conf);
            }

            $h = $email->add()->hotel();

            // General
            $h->general()->noConfirmation();

            $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

            // GuestNames
            $firstNames = $this->http->XPath->query(".//span[contains(@class, 'first-name')]", $gRoot);
            $guestNames = [];

            foreach ($firstNames as $root) {
                if ($this->http->FindSingleNode("./following-sibling::span[contains(@class, 'last-name')][1]",
                        $root) || $this->http->FindSingleNode("./preceding-sibling::span[contains(@class, 'last-name')][1]",
                        $root)) {
                    if ($this->lang == 'ja') {
                        $guestNames[] = $this->http->FindSingleNode(".",
                                $root) . ' ' . $this->http->FindSingleNode("./preceding-sibling::span[contains(@class, 'last-name')][1]",
                                $root);
                    } else {
                        $guestNames[] = $this->http->FindSingleNode(".",
                                $root) . ' ' . $this->http->FindSingleNode("./following-sibling::span[contains(@class, 'last-name')][1]",
                                $root);
                    }
                }
            }

            if (count($guestNames) == 0) {
                if (!empty($statusText = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('cancelled'))} or (contains(., 'cancellation.email.green.success.message.single.room.unhotelling'))]", $gRoot))) {
                    if ($this->lang == 'ja') {
                        $guestNames = array_filter($this->http->FindNodes(".//text()[{$this->starts($this->t('The room is in the name of'))}]/preceding::text()[normalize-space()][1]",
                            $gRoot, "/^{$patterns['travellerName']}$/u"));
                    } else {
                        $guestNames = array_filter($this->http->FindNodes(".//text()[{$this->starts($this->t('The room is in the name of'))}]/following::text()[normalize-space()][1]",
                            $gRoot, "/^{$patterns['travellerName']}$/u"));
                    }
                    $h->general()
                        ->cancelled();

                    if (preg_match("/{$this->preg_implode($this->t('StatusCancelled'), false)}/iu", $statusText, $m) && !empty($m[1])) {
                        $status = $m[1];
                    }

                    if (empty($status)) {
                        $status = $this->re("/we’ve (cancelled|canceled|cancell?ed the last room|cancell?ed one of the rooms)(?: in)? your booking/i",
                            $statusText);
                    }

                    if (!empty($status)) {
                        $h->general()->status($status);
                    }
                }
            }

            if (count($guestNames) == 0 && !$h->getCancelled()) {
                if (count($guestNames = array_filter($this->http->FindNodes(".//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::table[1]/following-sibling::table[1]",
                        $gRoot, "#^(.*?)(?:様、|，|,)#"))) == 0) {
                    if (count($guestNames = array_filter($this->http->FindNodes(".//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::td[1]/following-sibling::td[1]",
                            $gRoot, "#^(.*?)(?:様、|，|,)#"))) == 0) {
                        if (count($guestNames = array_filter($this->http->FindNodes(".//td[" . $this->eq($this->t("Room:")) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][last()]",
                                $gRoot, "#(\D*?)(?:様、|，|,)#u"))) == 0) {
                            $guestNames = array_filter(array_map('trim', explode(",", preg_replace("#\s+#", " ",
                                implode(" ",
                                    $this->http->FindNodes(".//td[{$this->eq($this->t("Room:"))}]/following-sibling::td[1]/span[./preceding-sibling::br][position()<last()] | //td[{$this->eq($this->t("Room:"))}]/following-sibling::td[1]/text()[./preceding-sibling::br][position()<last()] | //td[{$this->eq($this->t("Room:"))}]/following-sibling::td[1]/p[2]/span[position()<last()]", $gRoot))))),
                                function ($v) {
                                    if (!empty($v) && preg_match("#^[^\d]{5,}$#", $v)) {
                                        return true;
                                    } else {
                                        return false;
                                    }
                                });

                            if (empty($guestNames)) {
                                $guestNames = $this->http->FindNodes(".//td[" . $this->starts($this->t("Room:")) . "]/following-sibling::td[1][count(*) = 2]/*[2]",
                                    $gRoot, "#^\s*([^,\d]{5,})(?:$|,|様、)#");
                            }
                        }
                    }
                }
            }

            $guestNames = array_values($guestNames);

            if (empty($guestNames[0])) {
                $guestNames = array_filter($this->http->FindNodes(".//*[@class = 'main-guest-full-name']", $gRoot)); // aeroplan
            }

            if (empty($guestNames[0])) {
                $guestName = $this->http->FindSingleNode('.//td[not(.//td) and ' . $this->starts($this->t("Dear ")) . ']',
                    $gRoot, true,
                    "/^\s*{$this->preg_implode($this->t("Dear "))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,.:;!]|$)/u");

                if ($guestName) {
                    $h->general()->traveller($guestName);
                }
            }

            if (!empty($guestNames[0])) {
                $h->general()->travellers(array_unique($guestNames));
            }

            $h->general()
                ->notes($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Important notices")) . "]/ancestor::td[1]/following-sibling::td[1]", $gRoot))
            ;

            // hotelName
            $hotelName = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Hotel Details")) . " or " . $this->eq($this->t("confirmation.mail.location.details.unhotelling")) . "]/ancestor::tr[1]/following-sibling::tr[1]", $gRoot);

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode('.//td[count(./table)=2]/table/descendant::tr[ not(.//text()[normalize-space(.)]) ]/descendant::img/@alt', $gRoot);
            }

            if ($hotelName) {
                $h->hotel()->name($hotelName);
                $xpathFragment1 = './/td[count(./table)=2]/table/descendant::tr[ ./descendant::a[normalize-space(.)="' . $hotelName . '"] ]';
            }

            // address
            $address = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Hotel Details"))} or {$this->eq($this->t("confirmation.mail.location.details.unhotelling"))}]/ancestor::tr[1]/following-sibling::tr[2][ {$this->contains($this->t("Phone:"))} or following-sibling::tr[normalize-space()][1][{$this->starts($this->t("Phone:"))}] ]",
                $gRoot, true, "#^(.+?)\s*(?:{$this->preg_implode($this->t("Phone:"))}|$)#");

            if (empty($address) && isset($xpathFragment1) && !empty($xpathFragment1)) {
                $addressTexts = $this->http->FindNodes($xpathFragment1 . '/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]', $gRoot);

                if ($addressTexts) {
                    $address = implode('', array_filter($addressTexts));
                }
            }

            if (empty($address) && !empty($hotelName)) {
                $addressTexts = $this->http->FindNodes('.//td[count(./table)=2]/table/descendant::a[normalize-space(.)="' . $hotelName . '"]/ancestor::*[1]/following-sibling::node()[normalize-space()][1]/descendant-or-self::text()[normalize-space()]', $gRoot);

                if ($addressTexts) {
                    $address = implode(', ', array_filter(array_unique($addressTexts), function ($v) {
                        return $v != ',';
                    }));
                }
            }

            if (empty($address) && !empty($hotelName)
                && ($addressTexts = $this->http->FindNodes('.//td[count(./table)=2]/table/descendant::a[normalize-space(.)="' . $hotelName . '"]/ancestor::p[1]/following-sibling::div[normalize-space(.)][1]', $gRoot))
            ) {
                $address = implode(', ', array_filter($addressTexts, function ($v) {
                    return $v != ',';
                }));
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Hotel Details")) . "]/ancestor::tr[1]/following-sibling::tr[2]", $gRoot);
            }

            if ($address) {
                $h->hotel()->address($address);
            }

            $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';

            // phone
            $phone = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Hotel Details")) . "]/ancestor::tr[1]/following-sibling::tr[position()<5][{$this->contains($this->t("Phone:"))}]",
                $gRoot, true, "#{$this->preg_implode($this->t("Phone:"))}\s*({$patterns['phone']})#");

            if (empty($phone) && isset($xpathFragment1) && !empty($xpathFragment1)) {
                $phone = $this->http->FindSingleNode($xpathFragment1 . '/following-sibling::tr[normalize-space(.)][2]',
                    $gRoot, true, "/^({$patterns['phone']})$/");
            }

            if (empty($phone) && !empty($hotelName)) {
                // it-56731979.eml
                $phone = $this->http->FindSingleNode('.//td[count(table)=2]/table/descendant::a[normalize-space()="' . $hotelName . '"]/ancestor::*[1]/following-sibling::node()[normalize-space()][2]',
                    $gRoot, true, "/^({$patterns['phone']})$/");
            }

            if ($phone) {
                $h->hotel()->phone($phone);
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("(.//td[" . $this->eq($this->t("Check in")) . "])[1]/following-sibling::td[normalize-space()]", $gRoot),
                    true))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("(.//td[" . $this->eq($this->t("Check out")) . "])[1]/following-sibling::td[normalize-space()]", $gRoot),
                    false));

            $guestsStr = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Room:")) . "]/following-sibling::td[1][" . $this->contains($this->t("adult")) . "]", $gRoot);

            if (!empty($guestsStr) && (preg_match("#(\d+)\s*" . $this->opt($this->t("adult")) . "#u", $guestsStr,
                        $m) || preg_match("#[,、]\s*" . $this->opt($this->t("adult")) . "\s(\d+)#u", $guestsStr, $m))) {
                $guests = $m[1];
            }

            if (empty($guests) && !$guests = array_sum($this->http->FindNodes(".//td[" . $this->contains($this->t("Room @"), "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . "]/following-sibling::td[1]",
                    $gRoot, "#(\d+)\s*" . $this->opt($this->t("adult")) . "#"))) {
                if (!$guests = array_sum($this->http->FindNodes(".//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::table[1]/following-sibling::table[1]",
                    $gRoot, "#(\d+)\s*" . $this->opt($this->t("adult")) . "#"))) {
                    $guests = array_sum(array_map(function ($item) {
                        return preg_match("#\b(\d{1,3})\s*{$this->opt($this->t("adult"))}#", $item,
                            $m) || preg_match("#{$this->opt($this->t("adult"))}\s*(\d{1,3})\b#", $item,
                            $m) ? $m[1] : null;
                    },
                        $this->http->FindNodes(".//text()[{$this->eq($this->t("Occupancy"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $gRoot)));
                }
            }

            if (isset($guests) && $guests !== null && $guests !== 0) {
                $h->booked()->guests($guests);
            }

            $kidsStr = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Room:")) . "]/following-sibling::td[1][" . $this->contains($this->t("kids")) . "]", $gRoot);

            if (!empty($kidsStr) && (preg_match("#(\d+)\s*" . $this->opt($this->t("kids")) . "#u", $kidsStr,
                        $m) || preg_match("#[,、]\s*" . $this->opt($this->t("kids")) . "\s(\d+)#u", $kidsStr, $m))) {
                $kids = $m[1];
            }

            if (empty($kids) && !$kids = array_sum($this->http->FindNodes(".//td[" . $this->contains($this->t("Room @"), "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . "]/following-sibling::td[1]",
                    $gRoot, "#(\d+)\s*" . $this->opt($this->t("kids")) . "#u"))) {
                if (!$kids = array_sum($this->http->FindNodes(".//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::table[1]/following-sibling::table[1]",
                    $gRoot, "#(\d+)\s*" . $this->opt($this->t("kids")) . "#u"))) {
                    $kids = array_sum(array_map(function ($item) {
                        return preg_match("#\b(\d{1,3})\s*{$this->opt($this->t("kids"))}#u", $item,
                            $m) || preg_match("#{$this->opt($this->t("kids"))}\s*(\d{1,3})\b#u", $item,
                            $m) ? $m[1] : null;
                    },
                        $this->http->FindNodes(".//text()[{$this->eq($this->t("Occupancy"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $gRoot)));
                }
            }

            if ($kids !== null && $kids !== 0) {
                $h->booked()->kids($kids);
            }

            $yourStay = $this->nextText($this->t("Your stay"));

            if (preg_match("#(?:\b|\D)(\d{1,3})[，]?\s*{$this->opt($this->t("room"))}#u", $yourStay, $m)
                || preg_match("#{$this->opt($this->t("room"))}\s+(\d{1,3})(?:\b|\D)#u", $yourStay, $m)
            ) {
                $h->booked()->rooms($m[1]);
            }

            if (!$roomType = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Room:")) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $gRoot)) {
                if (!$roomType = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Room:")) . "]/ancestor::table[1]/following-sibling::table[1]/*[last()]/descendant::text()[normalize-space(.)][1]", $gRoot)) {
                    if (is_array($this->t("Room @"))) {
                        foreach ($this->t("Room @") as $roomTitle) {
                            if (!$roomType = $this->http->FindNodes(".//td[" . $this->contains($roomTitle, "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . " and string-length(normalize-space())<" . (strlen($roomTitle) + 3) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $gRoot)) {
                                $roomType = $this->http->FindNodes(".//text()[" . $this->contains($roomTitle, "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . " and string-length(normalize-space())<" . (strlen($roomTitle) + 3) . "]/ancestor::table[1]/following-sibling::table[1]/descendant::text()[normalize-space(.)][1]", $gRoot);
                            }
                        }
                    } else {
                        if (!$roomType = $this->http->FindNodes(".//td[" . $this->contains($this->t("Room @"), "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . " and string-length(normalize-space())<" . (strlen($this->t("Room @")) + 3) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $gRoot)) {
                            $roomType = $this->http->FindNodes(".//text()[" . $this->contains($this->t("Room @"), "translate(normalize-space(.), '1234567890', '@@@@@@@@@@')") . " and string-length(normalize-space())<" . (strlen($this->t("Room @")) + 3) . "]/ancestor::table[1]/following-sibling::table[1]/descendant::text()[normalize-space(.)][1]", $gRoot);
                        }
                    }
                }
            }

            if (is_string($roomType)) {
                $roomType = [$roomType];
            }

            if (empty($roomType) && !empty($this->http->FindSingleNode(".//h1[" . $this->eq($this->t("Room detailsH1")) . " or normalize-space()='confirmation.mail.roomdetails.description.unhotelling']", $gRoot))) {
                //  ограничение при двух и более резерваций
                if ($nodes->length > 1) {
                    $not = "following::text()[" . $this->contains($this->t("confirmation number")) . "]/ancestor::*[" . $this->contains($this->t("Hotel Details")) . "][1]";
                    $roomType = $this->http->FindNodes(".//h1[" . $this->eq($this->t("Room detailsH1")) . " or normalize-space()='confirmation.mail.roomdetails.description.unhotelling']/ancestor::tr[1]/following::tr[count(.//td[normalize-space()]) = 1 and .//h2]"
                        . "[./following::tr[1][.//td[1][" . $this->eq($this->t("Occupancy")) . "]]]"
                        . "[count({$not}) = " . ($nodes->length - $ni) . "]", $gRoot);
                } else {
                    $roomType = $this->http->FindNodes(".//h1[" . $this->eq($this->t("Room detailsH1")) . " or normalize-space()='confirmation.mail.roomdetails.description.unhotelling']/ancestor::tr[1]/following::tr[count(.//td[normalize-space()]) = 1 and .//h2][./following::tr[1][.//td[1][" . $this->eq($this->t("Occupancy")) . "]]]",
                        $gRoot);
                }
            }

            foreach ((array) $this->t("Room details") as $phrase) {
                $roomTypeDesc = $this->http->FindNodes(".//td[({$this->eq($phrase)}) and not(.//ancestor::h1)]/following-sibling::td[1]/descendant::strong[1]/following::text()[normalize-space()][1][not(ancestor::b)]", $gRoot);

                if (count($roomTypeDesc) > 0) {
                    break;
                }
            }

            if (count($roomTypeDesc) === 0) {
                foreach ((array) $this->t("Room details") as $phrase) {
                    $roomTypeDesc = $this->http->FindNodes(".//td[({$this->eq($phrase)}) and not(.//ancestor::h1)]/following::text()[normalize-space()][1]", $gRoot);

                    if (count($roomTypeDesc) > 0) {
                        break;
                    }
                }
            }

            foreach ((array) $this->t("Room details") as $phrase) {
                $roomTypeDescExt = $this->http->FindNodes(".//td[({$this->eq($phrase)}) and not(.//ancestor::h1)]/following-sibling::td[1]/descendant::strong[1]", $gRoot);

                if (count($roomTypeDescExt) > 0) {
                    break;
                }
            }

            if (count($roomTypeDesc) !== count($roomTypeDescExt)) {
                $roomTypeDescExt = [];
            }

            $roomType = array_filter(array_map(function ($e) {
                if (!preg_match('/[\<\>\=]+/', $e) > 0) {
                    return $e;
                }

                return null;
            }, $roomType));

            if (!empty($roomType) && !empty($roomTypeDesc) && count($roomType) == count($roomTypeDesc)) {
                foreach ($roomType as $key => $type) {
                    $room = $h->addRoom();

                    if ($this->http->XPath->query("//text()[{$this->eq($this->t('Price per room per night'))}]")->length > 0) {
                        $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price per room per night'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

                        if (!empty($rate)) {
                            $room->setRate($rate . ' / night');
                        }
                    } else {
                        $roomNumber = $key + 1;
                        $rate = implode(' / night, ', $this->http->FindNodes("//text()[{$this->eq($this->t('Price for room ') . $roomNumber)}]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr/td[2]"));

                        if (!empty($rate)) {
                            $room->setRate($rate . ' / night');
                        }
                    }

                    $room->setType($type);

                    if (isset($roomTypeDescExt[$key])) {
                        $room->setDescription($roomTypeDescExt[$key] . ', ' . $roomTypeDesc[$key]);
                    } else {
                        $room->setDescription($roomTypeDesc[$key]);
                    }
                }
            } elseif (!empty($roomType)) {
                foreach ($roomType as $key => $type) {
                    $room = $h->addRoom();

                    $room->setType($type);

                    $roomNumber = $key + 1;
                    $rate = implode(' / night, ', $this->http->FindNodes("//text()[{$this->eq($this->t('Price for room ') . $roomNumber)}]/following::text()[normalize-space()][1]/ancestor::table[1]/descendant::tr/td[2]"));

                    if (!empty($rate)) {
                        $room->setRate($rate . ' / night');
                    }
                }
            } elseif (!empty($roomTypeDesc)) {
                foreach ($roomTypeDesc as $key => $typeDesc) {
                    if (isset($roomTypeDescExt[$key])) {
                        $h->addRoom()->setDescription($roomTypeDescExt[$key] . ', ' . $roomTypeDesc[$key]);
                    } else {
                        $h->addRoom()->setDescription($roomTypeDesc[$key]);
                    }
                }
            }

            // CancellationPolicy
            $cancellationTexts = $this->http->FindNodes("descendant::div[normalize-space(@data-content-description)='cancellation-policy']/descendant::text()[normalize-space()]", $gRoot);

            if (count($cancellationTexts) === 0) {
                $cancellationTexts = $this->http->FindNodes("descendant::td[{$this->eq($this->t("Cancellation policy"))}][last()]/following-sibling::td[1]/descendant::text()[normalize-space()]", $gRoot);
            }

            if (count($cancellationTexts) === 0) {
                // it-2805374.eml
                $cancellationTexts = $this->http->FindNodes("descendant::table[{$this->eq($this->t("Cancellation policy"))}]/following-sibling::table[1]/descendant::text()[normalize-space()]", $gRoot);
            }

            if (count($cancellationTexts) > 1 && preg_match("/^\s*[[:alpha:]\-]+( [[:alpha:]\-]+){0,3}\s*$/u", $cancellationTexts[0])
                && preg_match("/^\s*[[:alpha:]\-]+( [[:alpha:]\-]+){0,3}\s*\d/u", $cancellationTexts[1])
            ) {
                $cancellationTexts[0] .= ' ' . $cancellationTexts[1];
                unset($cancellationTexts[1]);
                $cancellationTexts = array_values($cancellationTexts);
            }
            $cancellationTexts = array_map(function ($item) {
                return preg_replace('/([^,.。;!?])\s*$/u', '$1.', ltrim($item, '-·,. '));
            }, $cancellationTexts);

            $cancellationPolicy = implode(' ', array_filter($cancellationTexts));

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = $this->nextText($this->t("Cancellation policy"));
                $cancel = $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Free cancellation until'))}])[1]/following::text()[string-length(normalize-space(.)) > 2][1]", $gRoot);

                if ($this->lang == 'zh') {
                    $cancel = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('Free cancellation until'))}])[1]/following::text()[string-length(normalize-space(.)) > 2][1]", $gRoot);
                }

                if (empty($cancel)) {
                    $cancel = $this->nextText2($this->t('Free cancellation until'));
                }

                if (!empty($cancel)) {
                    $cancellationPolicy .= " " . $cancel;
                }
            }

            if (!empty($cancellationPolicy)) {
                $h->general()->cancellation($cancellationPolicy);

                $cancellationPolicy2 = $this->http->FindSingleNode("(.//td[" . $this->eq($this->t("Cancellation policy")) . "])[1]/following-sibling::td[1]", $gRoot);
                // deadline
                $this->detectDeadLine($h, $cancellationPolicy . "\n" . $cancellationPolicy2);
            }

            if (1 === $this->http->XPath->query("(.//text()[contains(normalize-space(.), 'as requested, we’ve canceled your booking') or contains(normalize-space(.), 'as requested, we’ve canceled one of the rooms in your booking') or contains(normalize-space(.), 'as requested, we’ve canceled the last room in your booking')][1])[1]", $gRoot)->length) {
                $h->general()
                    ->cancelled();
            }

            // Price

            $paids = $this->http->FindNodes("(.//text()[{$this->eq($this->t("Payment schedule"))} or normalize-space()='confirmation.mail.payment_details.payment_schedule.title'])[1]/ancestor::*[{$this->starts($this->t("Payment schedule"))} or starts-with(normalize-space(), 'confirmation.mail.payment_details.payment_schedule.title')][last()]//tr[not(.//tr)][normalize-space()]/*[normalize-space()][last()]",
                $gRoot, "/.*\d.*/");

            if (!empty($paids)) {
                // block "Payment schedule"
                $total = 0.0;
                $currency = $currencySign = null;

                foreach ($paids as $p) {
                    $t = $this->normalizePrice($p, $currencySign, $h->getAddress());

                    if ((float) $t['amount'] === 0.0) {
                        continue;
                    }

                    if ($currencySign === null || $currencySign == $t['currencySign']) {
                        $total += $t['amount'];
                        $currency = $t['currency'];
                        $currencySign = $t['currencySign'];
                    } else {
                        $total = 0;
                        $currency = $currencySign = null;

                        break;
                    }
                }
            }

            if (!empty($total)) {
                $h->price()
                    ->total($total)
                    ->currency($currency)
                ;
            }

            if (empty($total)) {
                $beforeHotel = "following::text()[{$this->eq($this->t("Hotel Details"))}]";
                $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[{$beforeHotel}][ count(*[normalize-space()])=2 and */descendant::text()[normalize-space()][1][{$this->eq($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][2][starts-with(normalize-space(), '(')]", $gRoot));

                if (empty($amountPaid)) {
                    $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[{$beforeHotel}][ count(*[normalize-space()])=2 and */descendant::text()[normalize-space()][1][{$this->eq($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]/ancestor::*[self::div or self::td or self::th or self::strong or self::b][1]", $gRoot));
                }

                if (empty($amountPaid)) {
                    $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[{$beforeHotel}][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][not({$this->contains($this->t("Including taxes and fees"))})]", $gRoot));
                }

                if (empty($amountPaid)) {
                    $amountPaid = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Amount paid"))}][{$beforeHotel}][1]", $gRoot);
                }

                if (!empty($amountPaid)) {
                    if (preg_match("/^\s*\d[\d, ]* miles\s*$/", $amountPaid)) {
                        $h->price()
                            ->spentAwards($amountPaid);
                    } else {
                        $price = $this->normalizePrice($amountPaid, null, $h->getAddress());
                        $currency = $price['currency'];
                        $currencySign = $price['currencySign'];
                        $h->price()
                            ->total($price['amount'])
                            ->currency($currency);
                    }
                }
            }

            if (empty($h->getPrice())) {
                $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and */descendant::text()[normalize-space()][1][{$this->eq($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][2][starts-with(normalize-space(), '(')]", $gRoot));

                if (empty($amountPaid)) {
                    $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and */descendant::text()[normalize-space()][1][{$this->eq($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]/ancestor::*[self::div or self::td or self::th or self::strong or self::b][1]", $gRoot));
                }

                if (empty($amountPaid)) {
                    $amountPaid = implode(' ', $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t("Amount paid"))}] ][last()]/*[normalize-space()][2]/descendant::text()[normalize-space()][not({$this->contains($this->t("Including taxes and fees"))})]", $gRoot));
                }

                if (empty($amountPaid)) {
                    $amountPaid = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Amount paid"))}][1]", $gRoot);
                }

                if (!empty($amountPaid)) {
                    $price = $this->normalizePrice($amountPaid, null, $h->getAddress());
                    $currency = $price['currency'];
                    $currencySign = $price['currencySign'];
                    $h->price()
                        ->total($price['amount'])
                        ->currency($currency)
                    ;
                }
            }

            if (!empty($h->getPrice()) && !empty($currencySign)) {
                $taxesAndFees = $this->http->FindSingleNode("(.//tr[not(.//tr)]/td[normalize-space()][1][descendant::text()[normalize-space()][{$this->eq($this->t("Taxes & fees"))}]])[1]/following-sibling::*[normalize-space(.)][1]", $gRoot);

                if (!empty($taxesAndFees) && !empty($currencySign)) {
                    $price = $this->normalizePrice($taxesAndFees, $currencySign, $h->getAddress());
                    $h->price()
                        ->tax($price['amount']);
                }

                $discountApplied = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Bon de réduction appliqué"))}][1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $gRoot,
                    null, '/^[- ]*(.*)$/');

                if (!empty($discountApplied) && !empty($currency)) {
                    // -16,63 $ CAD
                    $price = $this->normalizePrice($discountApplied, $currencySign, $h->getAddress());
                    $h->price()
                        ->discount($price['amount']);
                }
            }

            $spents = $this->http->FindNodes(".//*[" . $this->contains($this->t('Hotels.com® Rewards reward* night applied')) . " and (not(./*) or not(.//*[" . $this->contains($this->t('Hotels.com® Rewards reward* night applied')) . "]))]", $gRoot);

            if (!empty($spents)) {
                $h->price()
                    ->spentAwards(count($spents) . ' night Hotels.com Rewards');
            }

            $membershipNumber = $this->http->FindSingleNode("descendant::node()[{$this->contains($this->t("membershipNumber"))}][last()]",
                $gRoot, true, "/{$this->opt($this->t("membershipNumber"))}\s+(\d{5,})(?:[,.;!?\s。]|$)/");

            if ($membershipNumber && !in_array($membershipNumber, array_column($email->getTravelAgency()->getAccountNumbers(), 0))) {
                $email->ota()->account($membershipNumber, false);
            }

            foreach ($email->getItineraries() as $it) {
                if ($h == $it) {
                    continue;
                }

                if ($h->toArray() === $it->toArray()) {
                    $email->removeItinerary($h);

                    break;
                }
            }
        }

        return $email;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        $regExps = (array) $this->t("#Free cancellation...#");

        foreach ($regExps as $regExp) {
            if (preg_match($regExp, $cancellationText, $m)) {
                if (!empty($m['date'])) {
                    if (
                        preg_match("#(\d{1,2})/(\d{1,2})/(\d{4})#", $m['date'], $mat) // 24/11/2018
                        && (
                            $this->lang !== 'en'
                            || (int) $mat[1] > 12
                            || $this->lang === 'en' && preg_match("/^.*\d.*,[ ]*(?:GB|IE)$/", $h->getAddress()) > 0
                            || ($this->lang === 'en' && $this->http->XPath->query("//a[contains(@href,'locale=en_GB')]")->length > 0)
                            || ($this->lang === 'en' && !empty($m['time']) && stripos($m['time'], 'm') === false && (int) $mat[2] <= 12)
                        )
                    ) {
                        $m['date'] = str_replace($mat[0], $mat[1] . '.' . $mat[2] . '.' . $mat[3],
                            $m['date']); // 24.11.2018
                    }
                    $m['date'] = preg_replace("#\b(\d{1,2})-(\d{1,2})-(\d{4})\b#", '$1.$2.$3', $m['date']);
                    $deadlineTime = $this->normalizeDate($m['date']);

                    if ($deadlineTime && !empty($m['time'])) {
                        $m['time'] = preg_replace('/(\d)[ ]*[-.][ ]*(\d)/', '$1:$2', $m['time']);
                        $m['time'] = preg_replace('/\b([ap])\.\s*m\./', '$1m', $m['time']);
                        $m['time'] = preg_replace('/(下午)([\d\:]+)/', '$2pm', $m['time']);

                        if (!empty($m['pm']) && stripos($m['time'], 'm') === false) {
                            $m['time'] .= 'pm';
                        }
                        $deadlineTime = strtotime($m['time'], $deadlineTime);
                    }

                    if ($deadlineTime > strtotime('2000-01-01')) {
                        $h->booked()
                            ->deadline($deadlineTime);

                        break;
                    }
                }
            }
        }

        if (empty($h->getDeadline()) && $this->lang == 'es') {
            if (preg_match("/\s*(?<time>\d+:\d+\s*a?p?\.?\s*m\.)\,\s*(?<date>[\d\/]+)\s*/", $cancellationText, $m)) {
                $h->setDeadline(strtotime(str_replace('/', '.', $m['date']) . ', ' . $this->normalizeTimes($m['time'])));
            }
        }
        $regExps = (array) $this->t("#Non-refundable-details#");

        foreach ($regExps as $regExp) {
            if (preg_match($regExp, $cancellationText, $m) && empty($h->getDeadline())) {
                $h->booked()->nonRefundable();

                return true;
            }
        }

        if (preg_match('#(?:' . implode('|', (array) $this->t("nonRefundableRegExp")) . ')#iu', $cancellationText) && empty($h->getDeadline())) {
            $h->booked()->nonRefundable();
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $dBody) {
            foreach ($dBody as $re) {
                if (is_string($re) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                    $this->lang = rtrim($lang, '1234567890');

                    return true;
                } elseif (is_array($re) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re[0] . '")]')->length > 0 && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re[1] . '")]')->length > 0) {
                    $this->lang = rtrim($lang, '1234567890');

                    return true;
                }
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

    private function normalizeTimes(?string $str, ?bool $isCheckIn = null)
    {
//        $this->logger->debug('$time in = '.print_r( $str,true));

        $timesDelimiters = '(?:\s|-|et|～|~|–)';
        $str = preg_replace('#^(\s*|.*[\s\-])(noon|정오|เที่ยง|midi|полдень|中午|正午|正午以前|öğlen|poludnie|midi|meio-dia|południe|middag|mediodía|meio-dia|middernacht)(\s*|[\s\-,].*)$#u',
            '${1}12:00${3}', $str);
        $str = preg_replace('#^(\s*|.*[\s\-])(når som helst)(\s*|[\s\-,].*)$#u',
            '${1}00:00${3}', $str);
        $str = preg_replace('#^(\s*|.*' . $timesDelimiters . ')(midnight|minuit|午夜|meia-noite|midnatt)(\s*|[\s\-].*|,.*)$#u', '${1}00:00${3}', $str);
        $str = preg_replace('#\b(下午|오후|μ\.μ\.)\b#u', 'PM', $str);
        $str = preg_replace('#\b(π\.μ)\b#u', 'AM', $str);

        $s = '^(?:\s*|\D* )';
        $e = '(?:\s*|[ ,]\D*|以降|부터|-\D*|–\D*)$';
        $timeFormat = [
            //4:00 p. m.
            "([\d\:]+)\s*([ap])\.\s*m\.",
            // Before 2 PM local time
            // 2
            "(\d{1,2})\s*([AP]M)",
            // 2
            "(\d{1,2})",
            // 2:30
            "(\d{1,2}:\d{2}(?:\s*[AP]M)?)",
            // 15.00 uur
            "(\d{1,2})[.](\d{2})",

            // 15h00
            // 11 h 00
            "(\d{1,2}) ?h ?(\d{2})",
            // 15:00:00
            "(\d{1,2}:\d{2}):\d{2}",
            //até 12h no horário local
            "(\d{1,2})h",
            //            "até (\d+)h no horário local",
            //            //15h-0h, horário local
            //            "(\d+)h\-\d+h\D+",
            //            //15:00-0h, horário local,
            //            "([\d\:]+)\-\d+h\D+",
        ];
        $in = array_map(function ($v) use ($s, $e) {
            return '#' . $s . $v . $e . '#iu';
        }, $timeFormat);
        $out = [
            "$1 $2m",
            "$1:00 $2",
            "$1:00",
            "$1",
            "$1:$2",

            "$1:$2",
            "$1",
            "$1:00",
            //            "$1:00",
            //            "$1",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$time out = '.print_r( $str,true));
        $re2times = "#{$s}(?<ci>" . implode('|', $timeFormat) . ")\D*\s*" . $timesDelimiters . "\s*\D*(?<co>" . implode('|', $timeFormat) . "){$e}#i";

        if (preg_match($re2times, $str, $m)) {
            if ($isCheckIn === true) {
                return preg_replace($in, $out, $m['ci']);
            } else {
                return preg_replace($in, $out, $m['co']);
            }
        }

        return $str;
    }

    private function normalizeDate(?string $str, ?bool $isCheckIn = null)
    {
        $str = preg_replace("/\s*\bconfirmation\.mail[a-z\d\._]+\b\s*/", ' ', $str);
        //$this->logger->debug('IN-' . $str);

        if (empty($str)) {
            return null;
        }

//        $this->logger->debug('$date in = '.print_r( $str,true));

        if (preg_match("#(.+)\(([^()]+)\)\s*$#u", $str, $m)) {
            $time = $this->normalizeTimes($m[2], $isCheckIn);
//            $this->logger->debug('Time Out ' . $time);
            if (!empty($time)) {
                $str = $m[1];
            }
        }

        $in = [
            // Monday, October 2, 2017
            "#^\s*[^\s\d]+,\s+([^\s\d]+)\s+(\d+),\s+(\d{4})\s*$#iu",
            //Sexta-feira, 4 de Agosto de 2017
            "#^\s*[^\s\d]+,\s+(\d+)\s+de\s+([^\s\d]+)\s+de\s+(\d{4})\s*$#u",
            //mercredi, 15 juillet 2015; srijeda, 8. kolovoza 2018.; domenica 5 novembre 2017 (...); יום רביעי, 27 פברואר, 2019 (14:00); torsdag den 16 november 2017(...)
            //четверг, 13 июня 2019 г.
            "#^\s*(?:יום )?[^\s\d]+(?: den)?[,\s]+(\d+)[.]?\s+([^\s\d,]+)[,]?\s+(\d{4})(?:\.|\s*г\.|)\s*$#ui",
            // Thursday, 19 October 17
            "#^\s*\w+,\s+(\d+)\s+(\w+)\s+(\d{2})\s*$#u",
            //2015 年 09 月 4 日, 星期五; 2018 年 07 月 27 日 (金曜日)  (...)
            "#^\s*(\d{4}) 年 (\d+) 月 (\d+) 日[, ]+\(?[^\s\d]+\)?\s*$#u",
            //2021 年 2 月 18 日, 星期四 （当地时间 14:30-23:30）
            "#^\s*(\d{4}) 年 (\d+) 月 (\d+) 日[, ]+\(?[^\s\d]+\)?\s*[ （]当地时间\s*(?:中午\s*)?([\d\:]+).+[）]$#u",

            //수, 08/08, 2018;
            "#^\s*[^\s\d]+,\s*(\d{1,2})/(\d{1,2}),\s*(\d{4})\s*$#u", /////////////////////////////////////////////////////////////////////////////
            //18 Mayıs 2018 Cuma (...)
            "#^\s*(\d+) ([^\s\d]+) (\d{4}) [^\s\d]+\s*$#u",
            // 2019. június 30., vasárnap
            "#^\s*(\d{4})\. (\w+) (\d{1,2})\.,\D*$#u",
            //6 Dec, 2019
            "#^\s*(\d+)[.]?\s+([^\s\d]+)[,]?\s+(\d{4})\s*$#ui",

            // С временем без скобок
            //2015-09-03 PM 6:00
            "#^\s*(\d{4})[-/](\d+)[-/](\d+)\s*([^\s\d]+)\s*(\d+:\d+)\s*$#u",
            //torsdag den 16 november 2017 11:00
            "#^\s*[^\s\d]+(?: den)?[,\s]+(\d+)[.]?\s+([^\s\d]+)[,]?\s+(\d{4})\s*(\d{1,2}:\d{2})\s*$#ui",

            //יום שישי, 10 ספטמבר, 2021 (14:00-0:00 (חצות) זמן מקומי
            "#^\D+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\s*[ (]\s*(\d+\:\d+).+$#u",
        ];

        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 20$3",
            "$3.$2.$1",
            "$3.$2.$1, $4",

            '$2.$1.$3',
            '$1 $2 $3',
            '$3 $2 $1',
            '$1 $2 $3',

            // С временем без скобок
            '$3.$2.$1, $5 $4',
            '$3.$2.$1, $4',
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{2,4}#u", $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1],
                    'da')) || ($en = MonthTranslate::translate($m[1], 'no'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }
//        $this->logger->debug('Date Out ' . $str);
        $str = strtotime($str);

        if (!empty($time) && !empty($str)) {
            $str = strtotime($time, $str);
        }

        //$this->logger->debug('IN-'.$str);

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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
    private function normalizePrice(?string $totalText, $currencySign = null, $address = null): array
    {
        $result = [
            'amount'       => null,
            'currency'     => null,
            'currencySign' => null,
        ];
        $totalText = trim($totalText, '*');

        $aRe = "(?<amount>\d[,.'\d ]*?)";
        $cRe = $currencySign ? "(?<currency>" . preg_quote($currencySign, '/') . ")" : "(?<currency>[^\d\)]{1,5})";
        $totalText = preg_replace("/.*\((\\$? *{$aRe}\s*{$cRe}|{$cRe}\s*{$aRe})\).*/J", '$1', $totalText);
        $totalText = preg_replace("/^\s*(\\$? *{$aRe}\s*{$cRe}|{$cRe}\s*{$aRe})\s*[*]?\(.+\)\s*$/J", '$1', $totalText);

        if (
            preg_match("/^\s*\\$\s*{$aRe}\s*(?<currency>[A-Z]{3})\s*$/", $totalText, $m)
            || preg_match("/^\s*(?:\D{2,10} )?{$aRe}\s*{$cRe}\s*$/", $totalText, $m)
            || preg_match("/^\s*(?:\D{2,10} )?{$cRe}\s*{$aRe}\s*$/", $totalText, $m)
        ) {
            $currency = $this->currency($m['currency'], $address);
            $result = [
                'amount'       => $this->normalizeAmount($m['amount'], $currency),
                'currency'     => $currency,
                'currencySign' => $m['currency'],
            ];
        }

        return $result;
    }

    private function normalizeAmount(?string $amount, $currency = null): ?float
    {
        $amount = PriceHelper::parse($amount, $currency);

        return is_numeric($amount) ? (float) $amount : null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextText2($field, $root = null)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s);
        }, $field)) . ')';
    }

    private function currency($s, $hotelAddress = '')
    {
        $sym = [
            '€'    => 'EUR',
            'NT$'  => 'TWD',
            'R$'   => 'BRL',
            'A$'   => 'AUD',
            'HK$'  => 'HKD',
            'S$'   => 'SGD',
            '$'    => 'USD',
            '£'    => 'GBP',
            '฿'    => 'THB',
            '₩'    => 'KRW',
            'TL'   => 'TRY',
            '￥'    => 'JPY',
            'Rs'   => 'INR',
            'Rp'   => 'IDR',
            'NZ$'  => 'NZD',
            'RM'   => 'MYR',
            'Ft'   => 'HUF',
            'Kč'   => 'CZK',
            'P'    => 'PHP',
            '₪'    => 'ILS',
            'kr'   => 'SEK',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^,.\d ]+)#", trim($s));

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                if ($f == '$') {
                    return $f;
                }

                if ($f == '￥' && preg_match("#,\s*CN\s*$#", $hotelAddress)) {
                    return 'CNY';
                }

                if ($f == 'kr') {
                    if (preg_match("#,\s*IS\s*$#", $hotelAddress)) {
                        return 'ISK';
                    } elseif (preg_match("#,\s*NO\s*$#", $hotelAddress)) {
                        return 'NOK';
                    } elseif (preg_match("#no#", $this->lang)) {
                        return 'NOK';
                    } else {
                        return $f;
                    }
                }

                return $r;
            }
        }

        return $f;
    }

    private function preg_implode($field, $quote = true)
    {
        if (empty($field)) {
            return 'false';
        }

        if (!is_array($field)) {
            $field = [$field];
        }

        if ($quote == true) {
            $field = array_map(function ($v) {
                return preg_quote($v, '#');
            }, $field);
        }

        return '(?:' . implode("|", $field) . ')';
    }
}
