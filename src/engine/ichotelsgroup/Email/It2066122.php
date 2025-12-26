<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2066122 extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-100381332.eml, ichotelsgroup/it-100381379.eml, ichotelsgroup/it-11225648.eml, ichotelsgroup/it-2066122.eml, ichotelsgroup/it-2066123.eml, ichotelsgroup/it-2066542.eml, ichotelsgroup/it-2067990.eml, ichotelsgroup/it-2072550.eml, ichotelsgroup/it-2072829.eml, ichotelsgroup/it-2073504.eml, ichotelsgroup/it-2087760.eml, ichotelsgroup/it-2177738.eml, ichotelsgroup/it-2191577.eml, ichotelsgroup/it-2328446.eml, ichotelsgroup/it-2352879.eml, ichotelsgroup/it-2352880.eml, ichotelsgroup/it-2667537.eml, ichotelsgroup/it-3034747.eml, ichotelsgroup/it-3108413.eml, ichotelsgroup/it-31731534.eml, ichotelsgroup/it-3389526.eml, ichotelsgroup/it-3451233.eml, ichotelsgroup/it-3457087.eml, ichotelsgroup/it-3458220.eml, ichotelsgroup/it-3458491.eml, ichotelsgroup/it-3464499.eml, ichotelsgroup/it-3477170.eml, ichotelsgroup/it-3479622.eml, ichotelsgroup/it-3479625.eml, ichotelsgroup/it-3481030.eml, ichotelsgroup/it-3481268.eml, ichotelsgroup/it-3482500.eml, ichotelsgroup/it-3486311.eml, ichotelsgroup/it-3490432.eml, ichotelsgroup/it-36064748.eml, ichotelsgroup/it-36239112.eml, ichotelsgroup/it-36239127.eml, ichotelsgroup/it-36635289.eml, ichotelsgroup/it-3985827.eml, ichotelsgroup/it-42945056.eml, ichotelsgroup/it-59414615.eml, ichotelsgroup/it-59755006.eml, ichotelsgroup/it-59874286.eml, ichotelsgroup/it-71854019.eml, ichotelsgroup/it-72605068.eml, ichotelsgroup/it-72868844.eml";
    public static $dictionary = [
        "en" => [
            //            "Reservation Confirmed" => "",
            "Reservation Cancelled" => ["Reservation Cancelled", "Reservation Canceled", "Your reservation has been cancelled."],
            //            "Reservation Updated" => "",
            //            "View Map" => ["View Map", "Email"],
            "Hotel Front Desk"             => ["Hotel Front Desk", "Hotel Front Desk:"],
            "Email"                        => ["Email:"],
            "Guest Name:"                  => ["Guest Name:", "Name:"],
            //            "Check In:" => "",
            "Rooms:" => ["Rooms:", "Suites:"],
            //            "Adults:" => "",
            //            "Children:" => "",
            "Your confirmation number is:" => ["Your confirmation number is:", "New confirmation number:", "YOUR CONFIRMATION NUMBER IS:", "Hotel confirmation number:", "Your confirmation number:"],
            //            "Cancellation number:" => "",
            "Rate Type:" => ["Room Rate Per Night:", "Rate Type:"],
            //            "Confirmation #:" => "",
            "Number of Rooms:" => ["Number of Rooms:", "Number of Suites:"],
            //            "Additional Guests:" => "",
            //            "Your reservation" => "", "has been cancelled" => "", // confirmation number for cancelled
            //            "Room Rate Per Night:" => "",
            "Taxes:"       => ["Taxes:", "Total Fees:", "Total Taxes:", "Service Charge:", "Extra Person Charge:"],
            "Total Price:" => ["Total Price:", "Estimated Total Price:"],
            //            "Nightly Points Cost:" => "",
            //            "Total Points Redeemed:" => "",
            "Member #:"                => ["Member #:", "Ambassador #:"],
            "Estimated points earned:" => ["Estimated points earned:", "Estimated Earnings:"],
            //            "Cancellation Policy:" => "",
        ],
        "zh" => [
            "Reservation Confirmed" => ["预订已确认。", "預訂已確認。"],
            "Reservation Cancelled" => ["预订已取消。", "預訂已取消。", "您的預訂已經取消。"],
            //            "Reservation Updated" => "",
            "View Map"                     => "查看地图/获取驾车路线",
            "Hotel Front Desk"             => ["酒店前台", "飯店櫃台:", "酒店前台 :"],
            "Email"                        => ["電子郵件:", '电子邮件:'],
            "Guest Name:"                  => ["宾客姓名 :", "登記入住 :"],
            "Check In:"                    => ["登记入住 :", "登記入住 :"],
            "Rooms:"                       => "客房 :",
            "Adults:"                      => "成人 :",
            "Children:"                    => "儿童 :",
            "Your confirmation number is:" => ["您的确认号码是：", "您的訂房代號是："],
            "Cancellation number:"         => ["取消号码：", "您的預訂已經取消。 取消代號 ："],
            "Rate Type:"                   => ["价格类型：", "房價類型："],
            //            "Confirmation #:" => "",
            "Number of Rooms:"         => ["客房数量：", "房間數量："],
            "Additional Guests:"       => "其他客人:",
            "Your reservation"         => ["您的预订", "您的預訂"], "has been cancelled" => ["已取消。", "已被取消。"], // confirmation number for cancelled
            "Room Rate Per Night:"     => ["每晚房价：", "每晚房價："],
            "Taxes:"                   => ["服务费：", "税费：", "服務費：", "额外费用 :"],
            "Total Price:"             => ["预计总价：", "預計總額："],
            "Nightly Points Cost:"     => "每晚消费积分 :",
            "Total Points Redeemed:"   => ["已兑换的积分总数：", "已兌換的總積分數："],
            "Member #:"                => ["会员帐号 :", "會員 #："],
            "Estimated points earned:" => ["优悦会积分赚取预计：", "總賺取積分："],
            "Cancellation Policy:"     => ["取消政策 :", "取消預訂政策 :"],
        ],
        "es" => [
            "Reservation Confirmed"        => "Reserva confirmada",
            "Reservation Cancelled"        => "Reserva cancelada",
            "Reservation Updated"          => ["Reserva actualizada", "Su reserva ha quedado modificada"],
            "View Map"                     => "Ver mapa",
            "Hotel Front Desk"             => ["Recepción del hotel", "Recepción del hotel:"],
            "Email"                        => ["Correo electrónico:"],
            "Guest Name:"                  => ["Nombre del hu", "Nombre del cliente:"],
            "Check In:"                    => "Entrada:",
            "Rooms:"                       => ["Habitaciones:", "Suites:"],
            "Adults:"                      => "Adultos:",
            "Children:"                    => "Niños:",
            "Your confirmation number is:" => ["Su número de confirmación es", "Nuevo número de confirmación:", "SU NÚMERO DE CONFIRMACIÓN ES"],
            "Cancellation number:"         => "Número de cancelación:",
            "Rate Type:"                   => "Tipo de tarifa:",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => "Número de habitaciones:",
            //            "Additional Guests:" => "",
            "Your reservation"     => "Su reserva", "has been cancelled" => ["ha quedado cancelada", 'se ha cancelado'], // confirmation number for cancelled
            "Room Rate Per Night:" => "Tarifa de habitación por noche:",
            "Taxes:"               => ["Tasas totales:", "Impuestos:", "Cargo por servicio:", 'Impuestos totales:', 'Cargos adicionales:'],
            "Total Price:"         => ["Precio total estimado:"],
            //            "Nightly Points Cost:" => "",
            "Total Points Redeemed:"   => "Puntos totales canjeados:",
            "Member #:"                => ["N.º de socio:", "Member #:"],
            "Estimated points earned:" => "Ganancias estimadas:",
            "Cancellation Policy:"     => ["Normas de cancelación:", "Política de cancelación:"],
        ],
        "de" => [
            "Reservation Confirmed"        => "Reservierung bestätigt",
            "Reservation Cancelled"        => "Ihre Reservierung wurde storniert",
            "Reservation Updated"          => "Reservierung aktualisiert",
            "View Map"                     => "Karte anzeigen",
            "Hotel Front Desk"             => ["Telefonnummer der Hotelrezeption", "Telefonnummer der Hotelrezeption:"],
            //            "Email"                        => [""],
            "Guest Name:"                  => "Name des Gastes:",
            "Check In:"                    => "Check-in:",
            "Rooms:"                       => ["Zimmer:", "Suiten:"],
            "Adults:"                      => "Erwachsene:",
            "Children:"                    => "Kinder:",
            "Your confirmation number is:" => ["Ihre Bestätigungsnummer ist:", 'Neue Bestätigungsnummer:'],
            "Cancellation number:"         => "Stornierungsnummer:",
            "Rate Type:"                   => "Tarifart:",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => ["Zahl der Zimmer:", "Zahl der Suiten:"],
            //            "Additional Guests:" => "",
            "Your reservation"         => "Ihre Reservierung", "has been cancelled" => "wurde storniert.", // confirmation number for cancelled
            "Room Rate Per Night:"     => "Zimmertarif pro Nacht:",
            "Taxes:"                   => ["Steuern:", "Servicegebühr:", "Zusätzliche Gebühren"],
            "Total Price:"             => ["Voraussichtlicher Gesamtpreis:"],
            "Nightly Points Cost:"     => "Aufgewendete Punkte pro Nacht:",
            "Total Points Redeemed:"   => "Eingelöste Punkte insgesamt:",
            "Member #:"                => "Mitglieds-Nr.:",
            "Estimated points earned:" => "Geschätzter Gewinn:",
            "Cancellation Policy:"     => "Stornierungsbedingungen:",
        ],
        "fr" => [
            "Reservation Confirmed" => "Réservation confirmée",
            "Reservation Cancelled" => "Votre réservation a été annulée",
            //            "Reservation Updated" => "",
            "View Map"                     => "Voir Plan",
            "Hotel Front Desk"             => ["Réception de l'hôtel", "Réception de l'hôtel :"],
            //            "Email"                        => [""],
            "Guest Name:"                  => "Nom du client :",
            "Check In:"                    => "Arrivée :",
            "Rooms:"                       => "Chambres :",
            "Adults:"                      => "Adultes :",
            "Children:"                    => "Enfants :",
            "Your confirmation number is:" => ["Votre numéro de confirmation est le :"],
            "Cancellation number:"         => "Numéro d'annulation :",
            "Rate Type:"                   => "Type de tarif :",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => "Nombre de chambres :",
            //            "Additional Guests:" => "",
            "Your reservation"         => "Votre réservation", "has been cancelled" => "a été annulée.", // confirmation number for cancelled
            "Room Rate Per Night:"     => "Prix de la chambre par nuit :",
            "Taxes:"                   => ["Frais supplémentaires:"],
            "Total Price:"             => ["Total (estimation) :"],
            "Nightly Points Cost:"     => "Coût en points par nuit :",
            "Total Points Redeemed:"   => "Total des points échangés :",
            "Member #:"                => "N° de membre :",
            "Estimated points earned:" => "Estimation des gains :",
            "Cancellation Policy:"     => "Politique d'annulation :",
        ],
        "it" => [
            "Reservation Confirmed" => "Prenotazione confermata",
            "Reservation Cancelled" => "La tua prenotazione è stata cancellata.",
            //            "Reservation Updated" => "",
            //            "View Map" => "",
            "Hotel Front Desk"             => ["Front Desk dell'hotel", "Front Desk", 'Front Desk dell\'hotel:'],
            //            "Email"                        => [""],
            "Guest Name:"                  => "Nome ospite:",
            "Check In:"                    => "Check In:",
            "Rooms:"                       => "Camere:",
            "Adults:"                      => "Adulti:",
            "Children:"                    => "Bambini:",
            "Your confirmation number is:" => "Il tuo numero di conferma è:",
            "Cancellation number:"         => "Numero di cancellazione:",
            "Rate Type:"                   => "Tipo di tariffa:",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => "Numero di camere:",
            //            "Additional Guests:" => "",
            //            "Your reservation" => "", "has been cancelled" => "", // confirmation number for cancelled
            "Room Rate Per Night:" => "Tariffa della camera a notte:",
            "Taxes:"               => ["Spese di servizio:", "Tasse:"],
            "Total Price:"         => ["Prezzo totale stimato:"],
            //            "Nightly Points Cost:" => "",
            //            "Total Points Redeemed:" => "",
            "Member #:"                => "Nº socio:",
            "Estimated points earned:" => "Punti stimati:",
            "Cancellation Policy:"     => "Politica di cancellazione:",
        ],
        "pt" => [
            "Reservation Confirmed" => "Reserva confirmada",
            "Reservation Cancelled" => "Sua reserva foi cancelada",
            //            "Reservation Updated" => "",
            "View Map"                     => "Exibir mapa",
            "Hotel Front Desk"             => ["Recepção do hotel", 'Recepção do hotel:'],
            "Email"                        => ["E-mail:"],
            "Guest Name:"                  => "Nome do hóspede:",
            "Check In:"                    => "Check-in:",
            "Rooms:"                       => ["Quartos:", "Apartamentos:"],
            "Adults:"                      => "Adultos:",
            "Children:"                    => "Crianças:",
            "Your confirmation number is:" => "O número da sua confirmação é:",
            "Cancellation number:"         => ["Número do cancelamento:", "Número do cancelamento:"],
            "Rate Type:"                   => "Tipo de tarifa:",
            //            "Confirmation #:" => "",
            "Number of Rooms:"     => "Número de quartos:",
            "Additional Guests:"   => "Convidados Adicionais:",
            "Your reservation"     => "Sua reserva", "has been cancelled" => "foi cancelada", // confirmation number for cancelled
            "Room Rate Per Night:" => ["Taxa do quarto por noite:", "Taxa do apartamento por noite:"],
            "Taxes:"               => ["Impostos:", "Custo de serviço:"],
            "Total Price:"         => ["Preço total estimado:"],
            //            "Nightly Points Cost:" => "",
            "Total Points Redeemed:"   => "Total de pontos trocados:",
            "Member #:"                => "Nº do associado:",
            "Estimated points earned:" => "Estimativa de ganhos:",
            "Cancellation Policy:"     => "Política de cancelamento:",
        ],
        "ru" => [
            "Reservation Confirmed" => "Бронирование подтверждено",
            "Reservation Cancelled" => "Ваше бронирование было отменено",
            //            "Reservation Updated" => "",
            "View Map"                     => "Посмотреть карту",
            "Hotel Front Desk"             => "Служба приема и размещения:",
            "Email"                        => ["Эл. почта:"],
            "Guest Name:"                  => "Имя гостя:",
            "Check In:"                    => "Заезд:",
            "Rooms:"                       => ["Номера:", "Номера Suite:"],
            "Adults:"                      => "Взрослые:",
            //            "Children:" => "",
            "Your confirmation number is:" => "Ваш номер подтверждения:",
            "Cancellation number:"         => "Номер отмены бронирования:",
            "Rate Type:"                   => "Тип тарифа:",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => ["Количество номеров:", "Количество номеров категории Suite:"],
            //            "Additional Guests:" => "",
            //            "Your reservation" => "", "has been cancelled" => "", // confirmation number for cancelled
            "Room Rate Per Night:" => ["Стоимость номера за ночь:", "Цена номера за ночь:"],
            "Taxes:"               => ["Налоги:"],
            "Total Price:"         => ["Ориентировочная итоговая цена:", "Примерная общая стоимость:"],
            //            "Nightly Points Cost:" => "",
            "Total Points Redeemed:" => "Всего использовано баллов:",
            "Member #:"              => "Пользователь #",
            //            "Estimated points earned:" => "",
            "Cancellation Policy:" => "Правила отмены бронирования:",
        ],
        "nl" => [
            "Reservation Confirmed" => "Reservering bevestigd",
            "Reservation Cancelled" => "Uw reservering is geannuleerd.",
            //            "Reservation Updated" => "",
            //            "View Map" => "",
            "Hotel Front Desk" => ["Hotelreceptie", "Hotelreceptie:"],
            //            "Email"                        => [""],
            "Guest Name:"      => "Naam gast:",
            "Check In:"        => "Inchecken:",
            "Rooms:"           => "Kamers:",
            "Adults:"          => "Volwassenen:",
            //            "Children:" => "",
            "Your confirmation number is:" => "Uw bevestigingsnummer is:",
            "Cancellation number:"         => "Annuleringsnummer:",
            "Rate Type:"                   => "Tarieftype:",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => "Aantal kamers:",
            //            "Additional Guests:" => "",
            "Your reservation"     => "Uw reservering", "has been cancelled" => "is geannuleerd.", // confirmation number for cancelled
            "Room Rate Per Night:" => "Kamertarief per nacht:",
            "Taxes:"               => ["Belastingen:"],
            "Total Price:"         => ["Geschatte totale kosten:"],
            //            "Nightly Points Cost:" => "",
            "Total Points Redeemed:" => "Totaal aantal punten ingewisseld:",
            "Member #:"              => "Lidnr.:",
            //            "Estimated points earned:" => "",
            "Cancellation Policy:" => "Annuleringsbeleid:",
        ],
        "ja" => [
            "Reservation Confirmed" => "ご予約確認のお知らせ。",
            "Reservation Cancelled" => "ご予約キャンセルのお知らせ。",
            //            "Reservation Updated" => "",
            //            "View Map" => "",
            "Hotel Front Desk" => ["ホテルフロントデスク", "ホテルフロントデスク:"],
            //            "Email"                        => [""],
            "Guest Name:"                  => ["お客様のお名前 :", "お客様のお名前"],
            "Check In:"                    => ["チェックイン :", "チェックイン"],
            "Rooms:"                       => ["客室数 :", "客室数"],
            "Adults:"                      => ["大人 :", "大人"],
            "Children:"                    => ["子供 :", "子供"],
            "Your confirmation number is:" => "ご予約確認番号：",
            "Cancellation number:"         => "キャンセル番号は",
            "Rate Type:"                   => "料金タイプ：",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => "客室数 :",
            //            "Additional Guests:" => "",
            "Your reservation"     => "", "has been cancelled" => "のご予約はキャンセルされました。", // confirmation number for cancelled
            "Room Rate Per Night:" => ["1泊ごとの客室料金：", "泊ごとの客室料金："],
            "Taxes:"               => "税金：",
            "Total Price:"         => "見積金額合計：",
            //            "Nightly Points Cost:" => "",
            "Total Points Redeemed:"   => "ポイント交換合計数：",
            "Member #:"                => ["アンバサダー会員番号：", "会員番号"],
            "Estimated points earned:" => "ポイント獲得数のお見積り：",
            "Cancellation Policy:"     => "キャンセルポリシー",
        ],
        "ko" => [
            "Reservation Confirmed" => "예약 확인됨",
            "Reservation Cancelled" => ["예약 취소 완료", "귀하의 예약이 취소되었습니다"],
            //            "Reservation Updated" => "",
            //            "View Map" => "",
            "Hotel Front Desk" => "호텔 프런트 데스크 :",
            //            "Email"                        => [""],
            "Guest Name:"      => "투숙객 이름 :",
            "Check In:"        => "체크인 :",
            "Rooms:"           => ["객실 :"],
            "Adults:"          => "성인 :",
            //            "Children:" => "",
            "Your confirmation number is:" => "귀하의 예약 확인 번호:",
            "Cancellation number:"         => "귀하의 취소 번호:",
            "Rate Type:"                   => "요금 종류 :",
            //            "Confirmation #:" => "",
            "Number of Rooms:" => ["객실 수 :"],
            //            "Additional Guests:" => "귀하의 취소 번호:",
            "Your reservation"         => "귀하의 예약(", "has been cancelled" => ")이 취소되었습니다.", // confirmation number for cancelled
            "Room Rate Per Night:"     => ["1박당 객실 요금 :"],
            "Taxes:"                   => ["세금 :", "추가 요금:"],
            "Total Price:"             => ["예상 총 요금 :"],
            "Nightly Points Cost:"     => "1박당 포인트 비용 :",
            "Total Points Redeemed:"   => "총 사용 포인트:",
            "Member #:"                => ["회원 번호 :", "Ambassador 번호 :"],
            "Estimated points earned:" => "예상 적립 포인트:",
            "Cancellation Policy:"     => "취소 정책 :",
        ],
    ];

    private $detectFrom = ['@reservations.ihg.com', '@intercontinental.com', '@tx.ihg.com'];

    private $subjectHotelName = ['Holiday Inn', 'InterContinental', 'Crowne Plaza', 'Staybridge Suites', 'Six Senses'];
    private $detectSubject = [
        'en' => 'Your Reservation Confirmation',
        'Your Reservation Cancellation',
        'Your Updated Reservation Confirmation',
        'Your IHG Business Rewards Pre HI Reservation',
        'Reservation pending: please confirm your reservation at',
        'es' => 'Su confirmación actualizada de reserva',
        'Su confirmación de reserva',
        'Su cancelación de reserva',
        'zh' => '的预订确认',
        '的预订取消',
        '的預訂取消',
        '的預訂確認',
        '的更新预订确认',
        '的更新預訂確認',
        'pt' => 'A confirmação da sua reserva',
        'ru' => 'Подтверждение Вашего бронирования за номером',
        'Отмена Вашего бронирования за номером',
        'de' => 'Bestätigung Ihrer Reservierung',
        'Bestätigung Ihrer aktualisierten Reservierung',
        'Stornierung Ihrer Reservierung',
        'fr' => 'Votre confirmation de réservation',
        'it' => 'Conferma della tua prenotazione',
        'Cancellazione della tua prenotazione',
        'ja' => 'ご予約確認のお知らせ',
        'ご予約キャンセルのお知らせ',
        'ko' => '예약 확인 번호',
        '예약 취소 번호',
        'nl' => ' Uw reserveringsbevestiging',
        'Uw reserveringsannulering',
    ];
    private $detectBody = [
        "en" => [
            "Thank you for choosing", "you for booking with", "Your reservation has been cancelled",
        ],
        "zh" => [
            "感谢您预订",
            "您的预订已取消",
            "预订已更新",
            "預訂已取消",
            "預訂已確認。",
            "您的預訂已經取消。",
            "感謝您預訂",
            "總賺取積分：",
            "优悦会积分赚取预计：",
            '檢視更多訂房詳細資訊',
        ],
        "es" => [
            "Gracias por reservar con",
            "Más ahorro para los socios de IHG® Rewards Club",
            "Ver más detalles de la reservación",
            'Ver más detalles de la reserva',
        ],
        "de" => [
            "Vielen Dank, dass Sie bei",
            "Ihre Reservierung wurde storniert",
        ],
        "fr" => [
            "Merci d'avoir réservé chez",
            "Votre réservation a été annulée",
        ],
        "it" => [
            "Grazie per aver prenotato con",
            "La tua prenotazione è stata cancellata",
        ],
        "pt" => [
            "Obrigado por sua reserva no",
            'Baixe o aplicativo do IHG',
        ],
        "ru" => [
            "Благодарим за бронирование отеля", "Благодарим Вас за бронирование в отеле",
        ],
        "ko" => [
            "에 예약해 주셔서 감사합니다",
            "귀하의 대기자 등록이 취소되었습니다",
        ],
        "nl" => [
            "Dank u wel voor uw boeking bij", "Dank u wel om voor','Reservering bijgewerkt", "Reservering geannuleerd",
        ],
        "ja" => [
            "いただき、ありがとうございます",
            "ご予約がキャンセルされました",
        ],
    ];

    private $dateFormatUS = false;
    private $lang = "en";
    private $langCancellation = "";  //IF TWO LANGS IN EMAIL ONLY!!!
    private $subject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (is_array($dict['Guest Name:'])) {
                foreach ($dict['Guest Name:'] as $guest) {
                    if (strpos($body, $guest) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            } else {
                if (strpos($body, $dict['Guest Name:']) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundFrom = false;

        foreach ($this->detectFrom as $dFrom) {
            if (stripos($headers['from'], $dFrom) !== false) {
                $foundFrom = true;

                break;
            }
        }

        if ($foundFrom === false) {
            $foundHotelName = false;

            foreach ($this->subjectHotelName as $sName) {
                if (stripos($headers['subject'], $sName) !== false) {
                    $foundHotelName = true;

                    break;
                }
            }

            if ($foundHotelName === false) {
                return false;
            }
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (stripos($body, 'ihg') === false && $this->http->XPath->query("//a[contains(@href,'ihg.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false || $this->http->XPath->query('//text()[contains(normalize-space(),"' . $dBody . '")]')->length > 0) {
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

    private function parseHtml(Email $email): void
    {
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        // Cancellation
        $cancellationPolicy = $this->http->findSingleNode("descendant::text()[{$this->starts($this->t("Cancellation Policy:"))}][1]/ancestor::tr[1]", null, true, "#" . $this->preg_implode($this->t("Cancellation Policy:")) . "[\s:]*(.+)#");

        if (!empty($cancellationPolicy)) {
            $h->general()->cancellation($cancellationPolicy);
            $this->detectDeadLine($h, $cancellationPolicy);
        }

        // General
        $confNo = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Your confirmation number is:"))}][1]/ancestor::tr[1]", null, true, "#{$this->preg_implode($this->t("Your confirmation number is:"))}\s*(\d{5,})\s*(?:[,.。]|$)?#");

        if (empty($confNo)) {
            $confNo = array_values(array_filter($this->http->FindNodes("//tr/td[5][{$this->eq($this->t("Confirmation #:"))}]/ancestor::tr[1]/following-sibling::tr/td[5]", null, "#^\s*(\d{5,})\s*$#")));
        }

        if (empty($confNo)) {
            $confNo = array_values(array_filter($this->http->FindNodes("(//tr[{$this->starts($this->t("Your confirmation number is:"))}])[1]", null, "#{$this->preg_implode($this->t("Your confirmation number is:"))}\s*(\d{5,})\s*$#")));
        }

        if (!empty($confNo) && is_array($confNo)) {
            foreach ($confNo as $v) {
                $h->general()
                    ->confirmation($v);
            }
        } elseif (!empty($confNo)) {
            $h->general()
                ->confirmation($confNo);
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t("Cancellation number:"))}][1]", null, true, "#{$this->preg_implode($this->t("Cancellation number:"))}\s*(\d{5,})#");

            if (!empty($confNo)) {
                $confOld = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Your reservation")) . "]/ancestor::td[1][" . $this->contains($this->t("has been cancelled")) . "]", null, "#" . $this->preg_implode($this->t("Your reservation")) . "\s*(\d{5,})\s*" . $this->preg_implode($this->t("has been cancelled")) . "#"))));

                if (count($confOld) === 1) {
                    $h->general()->confirmation($confOld[0], null, true);
                }

                $h->general()->cancellationNumber($confNo, rtrim(((array) $this->t("Cancellation number:"))[0], ':'));
                $h->general()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t("Your pending confirmation number is"))}][1]", null, true, "#{$this->preg_implode($this->t("Your pending confirmation number is"))}\s*(\d{5,})#");

            if (!empty($confNo)) {
                $h->general()->confirmation($confNo, rtrim(((array) $this->t("pending confirmation number"))[0], ':'));
            }
        }

        if (empty($confNo)) {
            if (preg_match("/ [#]\s*(\d{6,}) /", $this->subject, $m)
                || preg_match("/(?:的更新預訂確認|确认号：)\s*(\d{6,})[。，]/u", $this->subject, $m)
            ) {
                $h->general()->confirmation($m[1]);
            }
        }

        $guestNames = [];
        $travellers1 = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Guest Name:"))}]/ancestor::td[1]", null, "#{$this->preg_implode($this->t("Guest Name:"))}[:\s]*(\D+)$#"));

        if (!empty($travellers1)) {
            $guestNames = array_merge($guestNames, $travellers1);
        }
        $travellers2 = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Additional Guests:"))}]/ancestor::td[1]//text()[normalize-space()][not({$this->eq($this->t("Additional Guests:"))})]"));

        if (!empty($travellers2)) {
            $guestNames = array_merge($guestNames, $travellers2);
        }
        $travellers3 = array_values(array_filter($this->http->FindNodes("//tr/td[1][{$this->eq($this->t("Guest Name:"))}]/ancestor::tr[1]/following-sibling::tr/td[1]", null, "#^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$#u")));

        if (!empty($travellers3)) {
            $guestNames = array_merge($guestNames, $travellers3);
        }

        if (count($guestNames)) {
            $guestNames = array_map(function ($item) {
                return ucwords(strtolower($item));
            }, $guestNames);
            $h->general()->travellers(array_unique($guestNames));
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Reservation Confirmed")) . "])[1]"))) {
            $h->general()->status('Confirmed');
        } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Reservation Cancelled")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Reservation Updated")) . "])[1]"))) {
            $h->general()->status('Updated');
        } elseif (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Pending reservation")) . "])[1]"))) {
            $h->general()->status($this->t("Pending reservation"));
        }

        // Hotel
        $name = $this->http->XPath->query("descendant::text()[{$this->contains($this->t("View Map"))} or {$this->contains($this->t("Hotel Front Desk"))}][1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr/descendant::a[contains(@href,'ihg.com') and string-length()>10]");

        if ($name->length === 0) {
            $name = $this->http->XPath->query("descendant::text()[{$this->contains($this->t("View Map"))} or {$this->contains($this->t("Hotel Front Desk"))}][1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr/descendant::a[string-length()>10]");
        }

        if ($name->length === 0) {
            // if hotel name is not link
            $name = $this->http->XPath->query("descendant::text()[{$this->contains($this->t("View Map"))} or {$this->contains($this->t("Hotel Front Desk"))}][1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr/descendant::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] ]");
        }

        if ($name->length === 0) {
            // if hotel name is not link

            $name = $this->http->XPath->query("descendant::text()[{$this->contains($this->t("Email"))}][1]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr/descendant::a[contains(@href,'ihg.com') and string-length()>9]");
        }

        if ($name->length === 0) {
            $name = $this->http->XPath->query("//text()[normalize-space()='Guest Name:']/preceding::a[1]");
        }

        foreach ($name as $root) {
            $name = $this->http->FindSingleNode("descendant::*[{$xpathBold}]", $root);

            if (empty($name)) {
                $name = trim(preg_replace('/\s+/', ' ', $root->nodeValue));
            }
            $addr = array_filter($this->http->FindNodes('ancestor::td[1]/descendant::text()[normalize-space()]', $root));
            array_shift($addr);
            $addr = implode(", ", $addr);
        }

        if (!empty($name) && !empty($addr)) {
            $name = htmlspecialchars($name);
            $name = str_replace(['amp;', '†'], '', $name);
            $h->hotel()
                ->name($name)
                ->address($addr)
            ;
        }

        $phone = trim($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Hotel Front Desk"))}][1]", null, true, "#" . $this->preg_implode($this->t("Hotel Front Desk")) . "[:\s]*([\d\-\+\(\) ]{5,})\s*$#"));

        if (empty($phone)) {
            $phone = trim($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Hotel Front Desk"))}][1]/following::text()[normalize-space()][1]", null, true, "#^[:\s]*([\d\-\+\(\) ]{5,})\s*$#"));
        }
        $h->hotel()
            ->phone($phone, true, true);

        // Booked
        $checkIn = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Check In:")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>7][1])[1]"));
        $checkOut = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Check In:")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[string-length(normalize-space(.))>7][2])[1]"));

        $this->dateFormatUS = $this->identifyDateFormat($checkIn, $checkOut);

        if ($this->dateFormatUS == true) {
            $checkIn = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $checkIn);
            $checkOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $checkOut);
        } else {
            $checkIn = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $checkIn);
            $checkOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $checkOut);
        }

        $time = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Check In:"))}][1]/following::td[contains(.,' PM') or contains(.,' AM')][1]");

        if (empty($time) or strlen($time) > 20) {
            $time = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Check In:"))}]/ancestor::table[1]/descendant::tr[last()]/descendant::td[string-length()>2][1]");
        }

        if (!empty($time) && !empty($checkIn) && strlen($time) < 15) {
            $checkIn .= ', ' . $this->normalizeTime($time);
        }

        $time = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Check In:"))}][1]/following::td[contains(.,' PM') or contains(.,' AM')][2]");

        if (empty($time) or strlen($time) > 20) {
            $time = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Check In:"))}]/ancestor::table[1]/descendant::tr[last()]/descendant::td[string-length()>2][2]");
        }

        if (!empty($time) && !empty($checkOut) && strlen($time) < 15) {
            $checkOut .= ', ' . $this->normalizeTime($time);
        }

        $h->booked()
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut))
            ->guests($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Adults:")) . "]/ancestor::tr[1]/following-sibling::tr[1])[1]", null, true, "#^\s*\d+\s+(\d+)#"), true, true)
            ->kids($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Children:")) . "]/ancestor::tr[1]/following-sibling::tr[1])[1]", null, true, "#^\s*\d+\s+\d+\s+(\d+)#"), true, true)
        ;

        $rooms = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Rooms:")) . "]/ancestor::tr[1]/following-sibling::tr[1])[1]", null, true, "#^\s*(\d+)\s+\d+#");

        if (empty($rooms)) {
            $rooms = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Number of Rooms:")) . "][1]", null, true, "#" . $this->preg_implode($this->t("Number of Rooms:")) . "\s+(\d+)\s*$#");
        }
        $h->booked()->rooms($rooms, true, true);

        // Program
        $memberNumbers = [];
        $accounts1 = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t("Member #:"))}]", null, "#{$this->preg_implode($this->t("Member #:"))}[\s:]*(\d{7,})\s*$#"));

        if (!empty($accounts1)) {
            $memberNumbers = array_merge($memberNumbers, $accounts1);
        }
        $accounts2 = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t("Member #:"))}]/following::text()[normalize-space() and not(normalize-space()=':')][1]", null, "#^[\s:]*(\d{7,})\s*$#"));

        if (!empty($accounts2)) {
            $memberNumbers = array_merge($memberNumbers, $accounts2);
        }
        $accounts3 = array_values(array_filter($this->http->FindNodes("//tr/td[3][{$this->eq($this->t("Member #:"))}]/ancestor::tr[1]/following-sibling::tr/td[3]", null, "#^\s*(\d{7,})\s*$#")));

        if (!empty($accounts3)) {
            $memberNumbers = array_merge($memberNumbers, $accounts3);
        }

        if (count($memberNumbers)) {
            $h->program()->accounts(array_unique($memberNumbers), false);
        }

        $earnedPoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Estimated points earned:"))}]/following::text()[normalize-space()][1]", null, true, "#^.*POINT.*$#i");
        $h->program()->earnedAwards($earnedPoints, false, true);

        // Rooms
        $r = $h->addRoom();
        $r
            ->setType($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Rate Type:"))}][1]/ancestor::tr[1]/preceding-sibling::tr[string-length(normalize-space())>1][1]/descendant::text()[normalize-space()][1]"))
            ->setRateType($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Rate Type:"))}][1]/following::text()[normalize-space()][1][ancestor::a]"), true, true)
        ;

        // ??
        $priceRegexp = "/^\s*\D{0,5}\s*(?<amount>(?<amount1>\d[\d,. ]*?)(?:[.,](?<amount2>\d{1,2}))?)\s*[^\d)(]{0,5}\s*\([ ]*(?<currencyCode>[A-Z]{3})[ ]*\)[*‡\s]*$/u";

        // (THB) ฿292,500.00
        $priceRegexp2 = "/^\([ ]*(?<currencyCode>[A-Z]{3})[ ]*\)[^\-\d)(]*(?<amount>\d[,.‘\'\d ]*)$/u";

        $ratesXpath = "//text()[" . $this->eq($this->t("Room Rate Per Night:")) . "]/following::text()[normalize-space()][1]/ancestor::tr[1]/ancestor::*[1]/*[normalize-space()]";
        $rateNodes = $this->http->XPath->query($ratesXpath);

        $freeNight = 0;
        $rateError = false;
        $rates = [];

        foreach ($rateNodes as $rRoot) {
            $dates = trim($this->http->FindSingleNode("td[1]", $rRoot));
            $rate = trim($this->http->FindSingleNode("td[2]", $rRoot));

            if ($dates === '' || $rate === '') {
                $rateError = true;

                break;
            }

            if (preg_match("#[:：]\s*$#u", $dates) || !preg_match("#.*\b(\d{4}|\d{1,2}/\d{2}/\d{2})\b.*$#", $dates)) {
                break;
            }

            $dateDiff = null;
            $dateDashFormatYmd = null;

            if (preg_match("#(.*\b\d{4}\b.*|\d{1,2}/\d{2}/\d{2}) - (.*\b\d{4}\b.*|\d{1,2}/\d{2}/\d{2})\s*$#", $dates)) {
                $rdates = explode(' - ', $dates);

                if (count($rdates) == 2) {
                    if ($dateDashFormatYmd === null) {
                        if (preg_match("#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#", $rdates[0], $m1)
                            && preg_match("#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#", $rdates[1], $m2)
                        ) {
                            // 2018-17-01; 2018-08-20
                            if ($m1[2] > 12 || $m2[2] > 12) {
                                $dateDashFormatYmd = false;
                            } elseif ($m1[3] > 12 || $m2[3] > 12) {
                                $dateDashFormatYmd = true;
                            } elseif ($m1[2] == $m2[2]) {
                                $dateDashFormatYmd = true;
                            } elseif ($m1[3] == $m2[3]) {
                                $dateDashFormatYmd = false;
                            }
                        }
                    }

                    if ($dateDashFormatYmd === false) {
                        $rdates = preg_replace("#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#", '$2.$3.$1', $rdates);
                    } elseif ($dateDashFormatYmd === false) {
                        $rdates = preg_replace("#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#", '$3.$2.$1', $rdates);
                    }
                    $d1 = strtotime($this->normalizeDate($rdates[0]));
                    $d2 = strtotime($this->normalizeDate($rdates[1]));

                    if (!empty($d1) && !empty($d2)) {
                        $dateDiff = date_diff(new \DateTime(date("j F Y", $d1)), new \DateTime(date("j F Y", $d2)))->days;

                        if ($dateDiff === 0) {
                            $dateDiff = 1;
                        } elseif (empty($dateDiff) || $dateDiff < 0) {
                            $dateDiff = null;
                            $rateError = true;
                        }
                    } else {
                        $rateError = true;
                    }
                } else {
                    $rateError = true;
                }
            } elseif (preg_match_all("/\d{4}/", $dates, $yearMatches) && count($yearMatches[0]) === 1) {
                $dateDiff = 1;
            } else {
                $rateError = true;
            }

            if ($rateError === true || empty($dateDiff)) {
                $rates = [];

                break;
            }

            if (preg_match("/^\s*(?:0|free|免费)\s*$/ui", $rate, $m)) {
                $rate = '0';
                $freeNight += $dateDiff;
            }

            $rates = array_merge($rates, array_fill(0, $dateDiff, $rate));
        }

        if (!empty($freeNight)) {
            $h->booked()->freeNights($freeNight);
        }

        if (!empty($rates) && $rateError == false) {
            $oneRate = array_unique($rates);

            if (count($oneRate) === 1) {
                $r->setRate($rates[0]);
            } else {
                if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                    $dateDiff = date_diff(new \DateTime(date("j F Y", $h->getCheckInDate())), new \DateTime(date("j F Y", $h->getCheckOutDate())))->days;

                    if ($dateDiff === count($rates)) {
                        $r->setRates($rates);
                    } else {
                        $this->logger->debug('the number of rates does not match the number of nights');
                    }
                }
            }
        } else {
            $rate = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Nightly Points Cost:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if (!empty($rate)) {
                $r->setRate($rate . ' Points ', true, true);
            }
        }

        // Price
        $taxesTitle = (array) $this->t("Taxes:");

        foreach ($taxesTitle as $title) {
            $tax = $this->http->FindSingleNode("descendant::text()[{$this->eq($title)}][1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

            if (preg_match($priceRegexp, $tax, $m)) {
                $amount = str_replace([' ', ',', '.'], '', $m['amount1']) . '.' . (empty($m['amount2']) ? '00' : $m['amount2']);
                $h->price()->fee(trim($title, ':：'), $this->amount($amount));
            }
        }

        $total = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Total Price:"))}][1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Total Credit Card Charge:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")
        ;

        if (preg_match($priceRegexp, $total, $matches) || preg_match($priceRegexp2, $total, $matches)) {
            if (!empty($matches['currencyCode'])) {
                $currencyCode = $matches['currencyCode'];
            } elseif (empty($matches['currencyCode']) && !empty($matches['currency'])) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            } else {
                $currencyCode = null;
            }

            $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];

            if (!empty($matches['amount'])) {
                $amount = $matches['amount'];
            } elseif (!empty($matches['amount1']) && !empty($matches['amount2'])) {
                $amount = str_replace([' ', ',', '.'], '', $matches['amount1']) . '.' . (empty($matches['amount2']) ? '00' : $matches['amount2']);
            } else {
                $amount = null;
            }

            $h->price()->total(PriceHelper::parse($amount, $currencyCode))->currency($currency);
        }

        $awards = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Points Redeemed:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($awards)) {
            $h->price()
                ->spentAwards($awards . ' Points ');
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("/O cancelamento da reserva antes de\s*(?<time>[\d\:]+)\s*\(hora local do hotel\)\s*em\s*\D+\,\s*(?<day>\d+)\s*(?<month>\w+)\,\s*(?<year>\d{4})\s*não resultará em cobrança/", $cancellationText, $m)) {
            $this->langCancellation = 'pt';
            $h->booked()
                ->deadline2($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $this->normalizeTime($m['time'])));
        }

        if (preg_match("/Cancelar sua reserva depois das/", $cancellationText, $m)) {
            $this->langCancellation = 'pt';
        }

        if (preg_match("#Canceling your reservation before (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(local hotel time\) on (?<date>.+?) will result in no charge\.#i", $cancellationText, $m) //en
            || preg_match("#Si cancela su reserva antes de las (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(hora local del hotel\) el (?<date>.+?), no se le cobrará recargo alguno\.#i", $cancellationText, $m) // es
            || preg_match("#O cancelamento da reserva antes de (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(hora local do hotel\) em (?<date>.+?) não resultará em cobrança\.#i", $cancellationText, $m) // pt
            || preg_match("#Vous pouvez annuler votre réservation avant (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(heure locale de l'hôtel\) le (?<date>.+?) sans frais\.#i", $cancellationText, $m) // fr
            || preg_match("#Отмена вашего бронирования до (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(местное время отеля\), (?<date>.+?) не влечет за собой списания средств\.#i", $cancellationText, $m) // ru
            || preg_match("#La cancellazione della prenotazione prima delle (?<time>\d{1,2}\.\d{1,2}) \(ora locale dell'albergo\) del (?<date>.+?) non comporterà alcun addebito\.#i", $cancellationText, $m) // it
            || preg_match("#Wenn Sie Ihre Reservierung vor (?<time>\d{1,2}:\d{1,2}(?:\s?[ap]m)?) \(Ortszeit\) am (?<date>.+?) stornieren, entstehen Ihnen keine Kosten\.#i", $cancellationText, $m) // de
            || preg_match("#在 (?<date>.{6,}?) [[:alpha:]]+ (?<time>.{3,}?)[(（]当地酒店时间[）)]之前取消预订不会收费 在当地时间#iu", $cancellationText, $m) // zh
            || preg_match("#在 (?<date>.+?)\s\w+\s(?<time>.+)[（]当地酒店时间[）]之前取消预订不会收费#iu", $cancellationText, $m) // zh
            || preg_match("#在 (?<date>.+?)\s\w+\s(?<time>.+)\s*[（]當地酒店時間#iu", $cancellationText, $m) // zh
            || preg_match("#ご予約を (?<date>.{5,}?日)[[:alpha:] ]+(?<time>.{3,}?) [(（]ホテルの現地時間[）)] 以前にキャンセルされた場合、キャンセル料はかかりません#iu", $cancellationText, $m) // ja
            || preg_match("#\b(?<date>\d{4}.{5,20}?)일 \(.+\) (?<time>.{3,}?)\(현지 호텔 시간\) 이전에 예약을 취소하면 요금이 부과되지 않습니다\.#iu", $cancellationText, $m) // ko
            || preg_match("#Indien u de reservering annuleert vóór (?<time>\d{1,2}:\d{1,2}) \(plaatselijke tijd hotel\) op zaterdag, (?<date>.+?), worden geen kosten in rekening gebracht\.#iu", $cancellationText, $m) // nl
        ) {
            $h->booked()
                ->deadline2($this->normalizeDate($m['date'] . ', ' . $this->normalizeTime($m['time'])));

            return;
        }

        if (preg_match("#取消预订或未能抵达，酒店将罚收您的订金。#i", $cancellationText) // zh
            || preg_match("#如果您取消预订或未露面，我们将通过您的信用卡收取每间 1 一个晚上 的费用。#i", $cancellationText, $m) // zh
            || preg_match("#Canceling your reservation or failing to arrive will result in forfeiture of your deposit\.#i", $cancellationText, $m) // en
            || preg_match("/Cancell?ing (?i)your reservation or failing to show will result in a charge for (?:1|the first) night per room to your credit card/", $cancellationText, $m) // en
            || preg_match("#Si richiede di non cancellare la prenotazione o presentarsi per evitare un addebito pari al deposito effettuato\.#i", $cancellationText, $m) // it
            || preg_match("#Als u uw reservering annuleert of als u niet komt, wordt per kamer 1 night op uw creditcard in rekening gebracht. \.#i", $cancellationText, $m) // nl
        ) {
            $h->booked()
                ->nonRefundable();

            return;
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function identifyDateFormat($date1, $date2)
    {
        if (preg_match("#(\d{1,2})[\/](\d{1,2})[\/](\d{4})#", $date1, $m)
                && preg_match("#(\d{1,2})[\/](\d{1,2})[\/](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return false;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return true;
            } else {
                $rateDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Rate Per Night:")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "#^\s*([^/]*\b\d{4}\b[^/]*) -(?:\s+.*\b\d{4}\b.*)?\s*$#")));

                if (!empty($rateDate)) {
                    $tempdate1 = strtotime(preg_replace("#(\d{1,2})[\/](\d{1,2})[\/](\d{4})#", '$1.$2.$3', $date1));
                    $tempdate2 = strtotime(preg_replace("#(\d{1,2})[\/](\d{1,2})[\/](\d{4})#", '$2.$1.$3', $date1));

                    if (!empty($tempdate1) && $tempdate1 !== $tempdate2 && abs($tempdate1 - $rateDate) < 60 * 60 * 24) {
                        return false;
                    } elseif (!empty($tempdate2) && $tempdate1 !== $tempdate2 && abs($tempdate2 - $rateDate) < 60 * 60 * 24) {
                        return true;
                    }
                }

                //try to guess format
                $diff = [];

                foreach ([0 => '$1.$2.$3', 1 => '$2.$1.$3'] as $i=>$format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false
                            && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('IN ' . $str);
        $in = [
            "#^(\d{4})/(\d+)/(\d+)$#", // 2018/01/17
            "#^(\d{1,2})/(\d{1,2})/(\d{2})$#", // 02/01/19
            "#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*$#u", // 2014年11月20日
            "#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日[\s,.]+(\d{1,2})[.:](\d{2}(?:\s*[AaPp][Mm])?)\s*$#u", // 2019年5月14日, 6:00 PM
            "#^\s*[[:alpha:]]*[,.\s]*(\d{1,2})[,.\s]+([[:alpha:]]{3,})[,.\s]+(\d{4})\s*$#u", // Mar. 29 Sept. 2015    |    28. März 2019

            "#^\s*[^\d\s]*,\s*(\d{1,2})\s+([^\d\s\.\,]+)[,\s]+(\d{4})[\s,.]+(\d{1,2})[\.:](\d{2}\s*(?:[ap]m)?)\s*$#iu", // Friday, 18 March, 2016, 6:00 PM    |    giovedì, 25 aprile, 2019, 18.00
            "#^\s*(\d{1,2})\s+([^\d\s\.\,]+)[,\s]+(\d{4})(?: г\.)?[\s,.]+(\d{1,2})[\.:](\d{2}\s*(?:[ap]m)?)\s*$#iu", // 19 February 2016 г., 18:00
            "#^\s*(\d{4})\s*년\s*(\d+)\s*월\s*(\d+)\s*$#", //2019년8월18
            "#^\s*(\d{4})\s*년\s*(\d+)\s*월\s*(\d+)[\s,.]+(\d{1,2})[.:](\d{2}(?:\s*[AaPp][Mm])?)\s*$#", //2019년8월18, 6:00 PM
            "#^\s*(?:[[:alpha:]]+\s+)?\s*(\d{1,2})\s*(\d{1,2})\s*(\d{4})$#", //04 12 2020, 금 11 12 2020

            "#^\s*(\d+)\s*(\d+)月\s*(\d{4})\s*$#u", // 週四 06 1月 2022
            "#^\s*[[:alpha:]]+[\s,]+(\d+)\s*([[:alpha:]]+月)\s*(\d{4})\s*$#u", // 週四 06 十月 2022; 06 十月 2022
            "#^\s*[[:alpha:]]+[\s,]+(\d+)\s+(\d+)\s+(\d{4})\s*$#u", // 水 26 10 2022
        ];
        $out = [
            "$3/$2/$1",
            "$1/$2/20$3",
            "$3.$2.$1",
            "$3.$2.$1, $4:$5",
            "$1 $2 $3",
            //            "$2.$3.$1",

            "$1 $2 $3, $4:$5",
            "$1 $2 $3, $4:$5",
            "$3-$2-$1",
            "$3-$2-$1, $4:$5",
            "$1.$2.$3",

            "$1.$2.$3",
            "$1 $2 $3",
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (!empty($this->langCancellation)) {
            if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
                if ($en = MonthTranslate::translate($m[1], $this->langCancellation)) {
                    $str = str_replace($m[1], $en, $str);
                }
            }
        } elseif (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        $this->logger->debug('OUT ' . $str);

        return $str;
    }

    private function normalizeTime(string $s): string
    {
        $s = preg_replace('/\b下午\s*(\d{1,2}:\d{2})\b/u', '$1 PM', $s); // 下午6:00    ->    6:00 PM
        $s = preg_replace('/\b오후\s*(\d{1,2}:\d{2})\b/u', '$1 PM', $s); // 오후 4:00    ->    6:00 PM
        $s = preg_replace('/\b(?:오전|上午)\s*(\d{1,2}:\d{2})\b/u', '$1 AM', $s); // 오후 4:00    ->    6:00 PM

        return $s;
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

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function amount($price)
    {
        $price = str_replace([' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }
}
