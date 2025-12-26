<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "agoda/it-120986589.eml, agoda/it-65301583.eml, agoda/it-66231754.eml, agoda/it-66733956.eml, agoda/it-67173088.eml, agoda/it-756369873.eml, agoda/it-756421500.eml, agoda/it-77475924.eml, agoda/it-94351388.eml, agoda/it-94840796.eml, agoda/it-94951719.eml, agoda/it-95033626.eml, agoda/it-95119137.eml, agoda/it-95432422.eml, agoda/it-95610105.eml, agoda/it-96397133.eml";

    private $reFrom = ['agoda.com'];
    private $reProvider = ['Agoda', 'Rocket Travel'];
    private $reSubject = [
        // en
        'Confirmation for Booking ID #',
        'Reminder for upcoming Booking ID',
        'Reminder for upcoming    Booking ID',

        'Bevestiging voor boekingsnummer #',
        // vi
        'Xác nhận cho mã số đặt phòng',
        //zh
        'Agoda訂房確認 - 預訂編號#',
        'Agoda訂單確認 ：預訂編號#',
        '訂單編號#',
        '確認訂單編號',
        '预订确认',
        '準備好出發了嗎？ 訂單編號',
        '你的Agoda行程預訂確認 (預訂編號：',
        'Agoda預訂確認信 - 訂單編號：',
        // es
        'Confirmación para la reserva ID #',
        'Confirmación de la reserva con Agoda - ID de reserva:',
        // pt
        'Confirmação da Reserva Nº',
        'Confirmação de reserva com a Agoda - ID de reserva:',
        'Confirmação de reserva com Agoda - ID da Reserva:',
        // ko
        '[아고다] 예약 확정 (예약 번호:',
        'Agoda 예약 확정서 - 예약 번호:',
        // id
        'Konfirmasi untuk ID Pesanan #',
        'Konfirmasi pesanan dengan Agoda - ID Pesanan:',
        // fr
        'Confirmation de la réservation #',
        // ru
        'Подтверждение бронирования №',
        'Подтверждение бронирования Agoda: №',
        //pl
        'Potwierdzenie rezerwacji nr',
        //th
        'อีเมลยืนยันการจองที่พัก | หมายเลขการจอง',
        'ใบยืนยันการจองของท่านกับ Agoda - หมายเลขการจอง',
        //de
        'Bestätigung für die Buchungsnummer',
        'Buchungsbestätigung für Ihre Reise mit Agoda – Buchungs-ID:',
        //ja
        '予約確定のお知らせ - 予約',
        'ご予約確認書 - 予約',
        '確認メール、予約',
        //ar
        'بتاريخ تسجيل وصول الجمعة',
        'تأكيد  لحجز رقم',
        //da
        'Bekræftelse af reservations-ID',
        // el
        'Επιβεβαίωση της κράτησης με ID',
        // fi
        'Vahvistus varaustunnukselle',
        //no
        'Bekreftelse av booking-ID',
        // it
        'Conferma della Prenotazione',
        'Conferma della modifica della prenotazione numero',
        'Conferma della prenotazione con Agoda - Numero di prenotazione:',
        // sv
        'Bekräftelse för Boknings-ID',
        // cs
        'Potvrzení rezervace č.',
        //lt
        'Užsakymo ID #',
        // uk
        'Підтвердження для бронювання з ID №',
        // ro
        'Confirmare pentru ID-ul Rezervării #',
        'Confirmare Modificare pentru ID-ul Rezervării #',
        // tr
        'Agoda rezervasyon onayı - Rezervasyon no:',
    ];
    private $reBody = [
        'en' => [
            ['Your booking is now confirmed', 'Reservation'],
            ['Your booking is confirmed', 'Reservations'],
            ['You have an upcoming trip!', 'Reservation'],
            ['We have confirmed the cancellation of your booking', 'Reservation'],
        ],
        'no' => [
            ['Bestillingen din er bekreftet!', 'Bestilling'],
        ],
        'vi' => [
            ['Để tham khảo, mã đặt phòng của quý khách là', 'Đặt phòng'],
        ],
        'zh' => [
            ['您的预订现已确认', '预订信息'],
            ['預訂成功。', '訂房摘要'],
            ['預訂成功，訂單已經獲得確認', '訂房摘要'],
            ['預訂概要', '你的預訂現已確認'],
            ['您的預訂已經完成並確認', '預訂細節：'],
            ['您的预订已成功确认！', '预订信息：'],
            ['旅程即將出發！', '訂房摘要'],
            ['你有即將入住的預訂！', '預訂概要'],
        ],
        'nl' => [
            ['Uw boeking is nu bevestigd', 'Reservering'],
        ],
        'es' => [
            ['Tu reserva está confirmada', 'Reserva'],
        ],
        'pt' => [
            ['A sua reserva está confirmada', 'Reserva'],
        ],
        'ko' => [
            ['고객님의 예약이 확정되었습니다', '객실 수 및 숙박 수'],
            ['고객님의 예약이 확정되고', '숙박일수 및 객실수'],
            ['예정된 여행이 있습니다!', '객실 수 및 숙박 수'],
        ],
        'id' => [
            ['Pesanan Anda berhasil dikonfirmasi', 'Reservasi'],
            ['Pesanan Anda telah dikonfirmasi dan selesai dibuat!', 'Pesanan'],
        ],
        'fr' => [
            ['Votre réservation est maintenant confirmée', 'Réservation'],
        ],
        'ru' => [
            ['Ваше бронирование подтверждено', 'Бронирование'],
        ],
        'pl' => [
            ['Twoja rezerwacja została już potwierdzona!', 'Rezerwacja'],
        ],
        'th' => [
            ['การจองของท่านได้รับการยืนยันแล้ว', 'จำนวนห้องและจำนวนคืน:'],
        ],
        'de' => [
            ['Ihre Buchung ist jetzt bestätigt!', 'Reservierung'],
            ['Ihre Buchung wurde bestätigt', 'Reservierung'],
        ],
        'ja' => [
            ['ご予約確定のお知らせ', '様'],
        ],
        'ar' => [
            ['حجزك مؤكد', 'حجز'],
        ],
        'he' => [
            ['הזמנתכם אושרה והושלמה', 'הזמנות'],
            ['ההזמנה שלכם מאושרת כעת', 'פרטי ההזמנה'],
        ],
        'da' => [
            ['Din reservation er nu bekræftet', 'Reservation'],
            ['Din reservation er bekræftet', 'Reservation'],
        ],
        'el' => [
            ['έχει πλέον επιβεβαιωθεί', 'Κράτηση'],
        ],
        'fi' => [
            ['Varauksesi on nyt vahvistettu', 'Varaustiedot'],
        ],
        'it' => [
            [' prenotazione è confermata', 'Prenotazione'],
        ],
        'cs' => [
            ['Vaše rezervace je nyní potvrzena', 'Rezervace'],
        ],
        'lt' => [
            ['Jūsų užsakymas patvirtintas', 'Rezervavimas'],
        ],
        'sv' => [
            ['Din bokning är nu bekräftad', 'Bokningsuppgifter'],
        ],
        'uk' => [
            ['Ваше бронювання тепер підтверджено', 'Бронювання'],
        ],
        'ro' => [
            ['Rezervarea este acum confirmată', 'Rezervare'],
        ],
        'tr' => [
            ['Rezervasyonunuz onaylandı', 'Giriş'],
        ],
    ];

    private $lang = '';

    private static $dictionary = [
        "en" => [
            'Hi'                             => ['Hi', 'Hello Mr', 'Hello'],
            'booking ID is'                  => ['booking ID is', 'Booking ID:', 'For reference, your booking ID is'],
            'Reservation'                    => ['Reservation', 'Reservations'],
            'room'                           => ['room', 'Room'],
            //        'Room type'                      => ['Room type'],
            'Check in'                       => ['Check in'],
            'Check out'                      => ['Check out'],
            'Lead guest'                     => ['Lead guest', 'Lead Guest'],
            'Occupancy'                      => ['Occupancy'],
            'adult'                          => ['adult', 'Adults', 'Adult'],
            'children'                       => ['children', 'child'],
            'Special request'                => ['Special request', 'Special requests:'],
            'Total price'                    => ['Total price', 'Total price:', 'Total Charge', 'Price Now'],
            'Cancellation and Change policy' => ['Cancellation and Change policy', 'Cancellation and Change Policy'],
            'Cancellation Policy'            => ['Cancellation Policy'],
            'Free Cancellation'              => ['Free Cancellation'],
            'Important Information'          => ['Important Information'],
        ],
        "vi" => [
            'Hi'                             => ['Chào'],
            'booking ID is'                  => ['quý khách là'],
            'Reservation'                    => 'Đặt phòng',
            'room'                           => 'phòng',
            'Room type'                      => 'Loại phòng',
            'Check in'                       => 'Nhận phòng',
            'Check out'                      => 'Trả phòng',
            'Lead guest'                     => 'Khách chính',
            'Occupancy'                      => 'Số người ở',
            'adult'                          => 'người lớn',
            'children'                       => 'trẻ em',
            // 'Special request' => '',
            'Total price'                    => 'Quý khách trả',
            'Cancellation and Change policy' => 'Chính sách Hủy và Thay Đổi',
            'Cancellation Policy'            => 'Chính Sách Hủy',
            'Free Cancellation'              => 'Hủy miễn phí',
            // 'Important Information' => '',
        ],
        "zh" => [
            'Hi'                             => ['您好', '嗨，'],
            'booking ID is'                  => ['您的订单号是', '您的訂單編號為', '訂單編號：', '你的參考預訂編號為', '預訂編號：', '预订编码：', '你的預訂編號為：', '您的预订编码是', '您的訂單編號為：'],
            'Reservation'                    => ['预订信息', '訂房摘要', '預訂概要', '預訂細節'],
            'room'                           => ['间客房', '間客房', '間房', '間客房', 'Room'],
            'Room type'                      => ['房型'],
            'Check in'                       => ['入住', '入住日期'],
            'Check out'                      => ['退房', '退房日期'],
            'Lead guest'                     => ['主要住客', '住客姓名', '顧客姓名', '顾客姓名'],
            'Occupancy'                      => ['入住人数', '入住人數', '入住人數限制'],
            'adult'                          => ['名大人', '位大人', '位成人', 'Adult'],
            'children'                       => ['名儿童', '位兒童', '位小童'],
            // 'Special request' => '',
            'Total price'                    => ['订单总价', '總價格', '總金額', '總金額：', '從支付卡扣除總金額：', '总价', '总价：', '現時價格', '当前价格'],
            'Cancellation and Change policy' => ['预订取消与更改条款', '訂單取消和更改政策', '取消與修改政策', '取消和更改政策', '取消及修改政策', '取消与修改政策'],
            'Cancellation Policy'            => ['取消政策', '预订取消政策'],
            'Free Cancellation'              => ['免費取消', '免费取消'],
            'Important Information'          => ['重要資訊', '重要信息'],
        ],
        "nl" => [
            'Hi'                             => ['Beste'],
            'booking ID is'                  => ['boekingsnummer is'],
            'Reservation'                    => 'Reservering',
            'room'                           => 'kamer',
            // 'Room type' => '',
            'Check in'                       => 'Inchecken',
            'Check out'                      => 'Uitchecken',
            //            'Lead guest' => '',
            'Occupancy'                      => 'Bezetting',
            'adult'                          => 'volwassene',
            'children'                       => 'kinderen',
            'Special request'                => 'Speciaal verzoek',
            'Total price'                    => 'Boekingswaarde',
            'Cancellation and Change policy' => 'Annulerings- en Wijzigingsbeleid',
            //        'Cancellation Policy'            => '',
            //        'Free Cancellation'              => '',
            // 'Important Information' => '',
        ],
        "es" => [
            'Hi'                             => ['Hola'],
            'booking ID is'                  => ['el ID de tu reserva es'],
            'Reservation'                    => 'Reserva',
            'room'                           => 'habitación',
            'Room type'                      => 'Tipo de habitación',
            'Check in'                       => 'Check-in',
            'Check out'                      => 'Check-out',
            'Lead guest'                     => 'Huésped principal',
            'Occupancy'                      => 'Capacidad',
            'adult'                          => ['adultos', 'adulto'],
            'children'                       => ['niños', 'niño'],
            //            'Special request' => '',
            'Total price'                    => ['Importe de la reserva', 'Cargo total', 'Precio actual'],
            'Cancellation and Change policy' => ['Política de Cancelación y Cambio'],
            'Cancellation Policy'            => ['Política de cancelación'],
            'Free Cancellation'              => ['Cancelación gratis'],
            'Important Information'          => 'Información importante',
        ],
        "pt" => [
            'Hi'                             => ['Olá,'],
            'booking ID is'                  => ['o seu ID de Reserva é:', 'O seu ID de reserva:'],
            'Reservation'                    => ['Reserva', 'Reservas'],
            'room'                           => ['quarto', 'Quarto'],
            'Room type'                      => 'Tipo de quarto',
            'Check in'                       => ['Check-in', 'Entrada'],
            'Check out'                      => ['Check-out', 'Saída'],
            'Lead guest'                     => ['Hóspede principal', 'Hóspede Principal'],
            'Occupancy'                      => ['Ocupação'],
            'adult'                          => ['adulto', 'Adultos'],
            'children'                       => ['crianças', 'criança'],
            //            'Special request' => '',
            'Total price'                    => ['Valor a pagar', 'Valor debitado no cartão', 'Preço total', 'Custo total para cartão', 'Preço agora'],
            'Cancellation and Change policy' => ['Política de cancelamento e alteração', 'Política de Cancelamento e Alterações'],
            'Cancellation Policy'            => ['Política de Cancelamento', 'Política de cancelamento'],
            'Free Cancellation'              => 'Cancelamento gratuito',
            'Important Information'          => 'Informações importantes',
        ],
        "ko" => [
            'Hi'                             => ['고객님, 안녕하세요.'],
            'booking ID is'                  => ['예약 번호는', '예약 번호:', 'your booking ID is'],
            'Reservation'                    => ['객실 수 및 숙박 수', '숙박일수 및 객실수'],
            'room'                           => '객실',
            'Room type'                      => '객실 종류',
            'Check in'                       => ['체크인', '체크인 날짜'],
            'Check out'                      => ['체크아웃', '체크아웃 날짜'],
            'Lead guest'                     => ['대표 투숙객', '투숙객 이름'],
            'Occupancy'                      => ['숙박 인원', '총 숙박 인원'],
            'adult'                          => '성인',
            'children'                       => '아동',
            //            'Special request' => '',
            'Total price'                    => ['총 금액', '총 카드 결제액:', '총 합계:', '현재 요금'],
            'Cancellation and Change policy' => ['예약 취소 및 변경 관련 정책', '취소 및 변경 정책'],
            'Cancellation Policy'            => '예약 취소 정책',
            'Free Cancellation'              => '예약 무료 취소',
            'Important Information'          => '[ 중요 사항 ]',
        ],
        "id" => [
            'Hi'                             => ['Halo '],
            'booking ID is'                  => ['ID Pesanan Anda adalah', 'ID Pesanan', 'ID Pesanan Anda:'],
            'Reservation'                    => 'Reservasi',
            'room'                           => 'kamar',
            'Room type'                      => 'Tipe kamar',
            'Check in'                       => 'Check-in',
            'Check out'                      => 'Check-out',
            'Lead guest'                     => ['Tamu utama', 'Tamu Utama'],
            'Occupancy'                      => ['Kapasitas kamar', 'Okupansi'],
            'adult'                          => 'dewasa',
            'children'                       => 'anak',
            //            'Special request' => '',
            'Total price'                    => ['Total harga', 'Total Harga', 'Harga Sekarang'],
            'Cancellation and Change policy' => 'Kebijakan Pembatalan dan Pengubahan',
            'Cancellation Policy'            => 'Kebijakan Pembatalan',
            'Free Cancellation'              => 'Pembatalan Gratis',
            'Important Information'          => 'Info Penting',
        ],
        "fr" => [
            'Hi'                             => ['Bonjour '],
            'booking ID is'                  => ['N° de réservation est', 'votre N° de réservation est le'],
            'Reservation'                    => 'Réservation',
            'room'                           => 'chambre',
            'Room type'                      => 'Catégorie de chambre',
            'Check in'                       => 'Arrivée',
            'Check out'                      => 'Départ',
            'Lead guest'                     => 'Hôte principal',
            'Occupancy'                      => "Nombre d'hôtes",
            'adult'                          => 'adulte',
            'children'                       => 'enfants',
            //            'Special request' => '',
            'Total price'                    => ['Valeur de la réservation', 'Prix total', 'Tarif actuel'],
            'Cancellation and Change policy' => 'Conditions d\'annulation et de modification',
            'Cancellation Policy'            => 'Conditions d\'annulation',
            'Free Cancellation'              => 'Annulation gratuite',
            'Important Information'          => 'Informations importantes',
        ],
        "ru" => [
            'Hi'                             => ['Здравствуйте,'],
            'booking ID is'                  => ['Его номер:', 'Номер вашего бронирования:', 'ID номер вашего бронирования:'],
            'Reservation'                    => 'Бронирование',
            'room'                           => 'номер',
            'Room type'                      => 'Тип номера',
            'Check in'                       => ['Заезд', 'Дата заезда'],
            'Check out'                      => ['Выезд', 'Дата выезда'],
            'Lead guest'                     => ['Основной гость', 'Имя гостя'],
            'Occupancy'                      => ['Вместимость', 'Размещение'],
            'adult'                          => ['взрослых', 'взрослый'],
            'children'                       => 'детей',
            //            'Special request' => '',
            'Total price'                    => ['Общая стоимость', 'Итого', 'Цена сейчас'],
            'Cancellation and Change policy' => 'Правила отмены и изменения',
            'Cancellation Policy'            => 'Правила отмены',
            'Free Cancellation'              => 'Бесплатная отмена',
            'Important Information'          => 'Важная информация',
        ],
        "pl" => [
            'Hi'                             => ['Здравствуйте,'],
            'booking ID is'                  => ['Numer ID Twojej rezerwacji to:'],
            'Reservation'                    => 'Rezerwacja',
            'room'                           => 'pokój',
            'Room type'                      => 'Typ pokoju',
            'Check in'                       => 'Zameldowanie',
            'Check out'                      => 'Wymeldowanie',
            'Lead guest'                     => 'Główny gość',
            'Occupancy'                      => 'Liczba osób',
            'adult'                          => 'dorosły',
            'children'                       => 'dzieci',
            //            'Special request' => '',
            'Total price'       => 'Łącznie do zapłaty',
            //        'Cancellation and Change policy' => '',
            'Cancellation Policy'            => 'Regulamin anulowania',
            'Free Cancellation'              => 'Darmowe anulowanie',
            // 'Important Information' => '',
        ],
        "th" => [
            'Hi'                             => ['เรียน คุณ'],
            'booking ID is'                  => ['หมายเลขการจองคือ'],
            'Reservation'                    => 'จำนวนห้องและจำนวนคืน',
            'room'                           => 'ห้อง',
            'Room type'                      => 'ประเภทห้องพัก',
            'Check in'                       => ['เช็คอิน', 'เช็คอิน:'],
            'Check out'                      => 'เช็คเอาต์',
            'Lead guest'                     => 'ชื่อผู้เข้าพัก',
            'Occupancy'                      => 'จำนวนผู้เข้าพัก',
            'adult'                          => 'ผู้ใหญ่',
            'children'                       => 'เด็ก',
            'Special request'                => 'คำขอรับบริการเพิ่มเติม:',
            'Total price'                    => ['ราคารวมทั้งสิ้น'],
            'Cancellation and Change policy' => 'นโยบายการยกเลิกและเปลี่ยนแปลงการจอง',
            'Cancellation Policy'            => 'นโยบายการยกเลิกการจอง',
            'Free Cancellation'              => 'ยกเลิกฟรี',
            'Important Information'          => 'ข้อมูลสำคัญ',
        ],
        "de" => [
            'Hi'                             => ['Hallo'],
            'booking ID is'                  => ['Ihre Buchungsnummer lautet', 'Ihre Buchungs-ID:'],
            'Reservation'                    => ['Reservierung'],
            'room'                           => 'Zimmer',
            'Room type'                      => 'Zimmertyp',
            'Check in'                       => ['Check-in', 'Check-In'],
            'Check out'                      => ['Check-out', 'Check-Out'],
            'Lead guest'                     => ['Hauptgast'],
            'Occupancy'                      => ['Belegung'],
            'adult'                          => ['Erwachsener', 'Erwachsene', 'Adults', 'Adult'],
            'children'                       => 'Kinder',
            //'Special request' => '',
            'Total price'                    => ['Gesamtbetrag', 'Gesamter Abbuchungsbetrag von Ihrer Karte:'],
            'Cancellation and Change policy' => 'Stornierungs- und Änderungsbedingungen',
            'Cancellation Policy'            => ['Stornierungsbedingungen'],
            'Free Cancellation'              => 'Kostenlose Stornierung',
            'Important Information'          => 'Wichtige Informationen',
        ],
        "ja" => [
            'Hi'                             => ['様'],
            'booking ID is'                  => ['お客様のご予約IDは「', '予約ID:'],
            'Reservation'                    => '予約内容',
            'room'                           => '部屋',
            'Room type'                      => 'ルームタイプ：',
            'Check in'                       => 'チェックイン日',
            'Check out'                      => 'チェックアウト日',
            'Lead guest'                     => '代表者名',
            'Occupancy'                      => '宿泊人数',
            'adult'                          => ['大人'],
            'children'                       => '子ども',
            //'Special request' => '',
            'Total price'       => ['合計お支払い金額', '現在の料金'],
            //        'Cancellation and Change policy' => '',
            'Cancellation Policy'            => 'キャンセルポリシー',
            'Free Cancellation'              => 'キャンセル無料',
            // 'Important Information' => '',
        ],
        "ar" => [
            'Hi'                             => ['مرحبًا'],
            'booking ID is'                  => ['رقم حجزك هو', 'رقم حجزك:'],
            'Reservation'                    => 'حجز',
            //            'room' => '',
            'Room type'                      => 'نوع الغرفة',
            'Check in'                       => ['تسجيل الوصول', 'تسجيل الدخول'],
            'Check out'                      => ['تسجيل المغادرة', 'تسجيل الخروج'],
            'Lead guest'                     => 'النزيل الرئيسي',
            'Occupancy'                      => 'الإشغال',
            'adult'                          => 'بالغون',
            'children'                       => 'من الأطفال',
            //            'Special request' => '',
            'Total price' => ['إجمالي المبلغ'],
            //        'Cancellation and Change policy' => '',
            'Cancellation Policy'            => 'سياسة الإلغاء',
            //        'Free Cancellation'              => '',
            // 'Important Information' => '',
        ],
        "he" => [
            'Hi'                             => ['היי'],
            'booking ID is'                  => ['מספר הזמנה:', 'לצפייה, ביטול או'],
            'Reservation'                    => ['הזמנות', 'תפוסה'],
            'room'                           => 'חדר',
            'Room type'                      => 'סוג החדר',
            'Check in'                       => ["צ'ק-אין"],
            'Check out'                      => ["צ'ק-אאוט"],
            'Lead guest'                     => ['האורח שעל שמו נעשתה ההזמנה', 'אורח ראשי'],
            'Occupancy'                      => ['תפוסה'],
            'adult'                          => ['מבוגר', 'ילדים'],
            'children'                       => 'מבוגרים',
            'Special request'                => 'בקשה מיוחדת',
            'Total price'                    => ['סך החיוב בכרטיס האשראי'],
            'Cancellation and Change policy' => 'מדיניות שינויים וביטולים',
            'Cancellation Policy'            => 'מדיניות ביטולים',
            'Free Cancellation'              => 'ביטול חינם',
            // 'Important Information' => '',
        ],
        "da" => [
            'Hi'                             => ['Hej'],
            'booking ID is'                  => ['Dit reservations-ID er', 'Dit reservations-ID:'],
            'Reservation'                    => ['Reservation', 'Reservationer'],
            'room'                           => 'værelse',
            'Room type'                      => 'Værelsestype',
            'Check in'                       => 'Indtjekning',
            'Check out'                      => 'Udtjekning',
            'Lead guest'                     => 'Hovedgæst',
            'Occupancy'                      => 'Belægning',
            'adult'                          => ['voksne', 'Adults'],
            'children'                       => 'børn',
            //'Special request' => '',
            'Total price'                    => ['Samlet pris', 'Samlet opkrævning fra kort:'],
            'Cancellation and Change policy' => 'Afbestillings- og ændringspolitik',
            'Cancellation Policy'            => 'Afbestillingspolitik',
            'Free Cancellation'              => 'Gratis afbestilling',
            // 'Important Information' => '',
        ],
        "el" => [
            'Hi'                             => ['Γεια σας,'],
            'booking ID is'                  => ['κράτησής σας είναι'],
            'Reservation'                    => 'Κράτηση',
            'room'                           => 'δωμάτιο',
            'Room type'                      => 'Τύπος δωματίου',
            'Check in'                       => 'Check-in',
            'Check out'                      => 'Check-out',
            'Lead guest'                     => 'Κύριος επισκέπτης',
            'Occupancy'                      => 'Χωρητικότητα',
            'adult'                          => ['ενήλικας'],
            'children'                       => 'παιδιά',
            //'Special request' => '',
            'Total price'       => ['Συνολική χρέωση'],
            //        'Cancellation and Change policy' => '',
            'Cancellation Policy'            => 'Πολιτική ακύρωσης',
            //        'Free Cancellation'              => '',
            // 'Important Information' => '',
        ],
        "fi" => [
            'Hi'                             => ['Moi'],
            'booking ID is'                  => ['Varaustunnuksesi on '],
            'Reservation'                    => 'Varaus',
            'room'                           => 'huone',
            'Room type'                      => 'Huonetyyppi',
            'Check in'                       => 'Tulo',
            'Check out'                      => 'Lähtö',
            'Lead guest'                     => 'Päävieras',
            'Occupancy'                      => 'Henkilömäärä',
            'adult'                          => ['aikuist'],
            'children'                       => 'lasta',
            'Special request'                => 'Erityispyyntö',
            'Total price'                    => ['Kokonaishinta'],
            //        'Cancellation and Change policy' => '',
            'Cancellation Policy'            => 'Peruutusehdot',
            'Free Cancellation'              => 'Maksuton peruutus',
            // 'Important Information' => '',
        ],
        "no" => [
            'Hi'            => ['Hei'],
            'booking ID is' => ['Booking-ID for bestillingen er'],
            'Reservation'   => 'Bestilling',
            'room'          => 'rom',
            'Room type'     => 'Romtype',
            'Check in'      => 'Ankomst',
            'Check out'     => 'Avreise',
            //            'Lead guest' => '',
            'Occupancy' => 'Antall gjester',
            'adult'     => 'voksne',
            //'children'                       => '',
            'Special request' => 'Forespørsler',
            'Total price'     => 'Totalpris',
            //'Cancellation and Change policy' => 'Annulerings- en Wijzigingsbeleid',
            'Cancellation Policy' => 'Avbestillingsregler',
            // 'Free Cancellation'              => '',
            'Important Information' => 'Viktige opplysninger',
        ],
        "it" => [
            'Hi'                             => ['Ciao'],
            'booking ID is'                  => ['Numero Prenotazione (booking ID):', 'Il numero di riferimento della prenotazione è'],
            'Reservation'                    => 'Prenotazione',
            'room'                           => 'camera',
            'Room type'                      => 'Tipo di camera',
            'Check in'                       => 'Check-in',
            'Check out'                      => 'Check-out',
            'Lead guest'                     => 'Ospite principale',
            'Occupancy'                      => ['Ospiti', 'Numero di ospiti'],
            'adult'                          => 'adult',
            'children'                       => 'bambini',
            //            'Special request' => 'Forespørsler',
            'Total price'                    => ['Prezzo totale:', 'Valore della prenotazione', 'Totale addebitato', 'Prezzo attuale'],
            'Cancellation and Change policy' => ['Termini di Cancellazione e Modifica', 'Cancellazioni e modifiche'],
            'Cancellation Policy'            => 'Politica di cancellazione',
            'Free Cancellation'              => 'Cancellazione gratuita',
            'Important Information'          => 'Informazioni importanti',
        ],
        "cs" => [
            //            'Hi'            => [''],
            'booking ID is'                  => ['Číslo vaší rezervace je'],
            'Reservation'                    => 'Rezervace',
            'room'                           => 'Pokoj:',
            'Room type'                      => 'Typ pokoje',
            'Check in'                       => 'Přihlášení k ubytování',
            'Check out'                      => 'Odhlášení z ubytování',
            'Lead guest'                     => 'Hlavní host',
            'Occupancy'                      => 'Obsazenost',
            'adult'                          => ['dospělí/ých', 'dospělý', 'Dospělých'],
            'children'                       => 'dětí',
            //            'Special request' => 'Forespørsler',
            'Total price'     => ['Celková částka', 'Aktuální cena'],
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy'            => 'Storno podmínky',
            'Free Cancellation'              => 'Bezplatné storno',
            'Important Information'          => 'Důležité informace',
        ],
        "lt" => [
            //            'Hi'            => [''],
            'booking ID is' => ['Jūsų užsakymo ID yra'],
            'Reservation'   => 'Rezervavimas',
            'room'          => 'kambarys',
            'Room type'     => 'Kambario tipas',
            'Check in'      => 'Atvykimas',
            'Check out'     => 'Išvykimas',
            'Lead guest'    => 'Pagrindinis svečias',
            'Occupancy'     => 'Vietų skaičius',
            'adult'         => 'suaugęs',
            //'children'                       => '',
            //            'Special request' => 'Forespørsler',
            'Total price'     => 'Bendra suma',
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy' => 'Atšaukimo sąlygos',
            //            'Free Cancellation' => '',
            // 'Important Information' => '',
        ],
        "sv" => [
            'Hi'            => ['Hej'],
            'booking ID is' => ['Ditt boknings-ID är'],
            'Reservation'   => 'Bokning',
            'room'          => 'rum',
            'Room type'     => 'Rumstyp',
            'Check in'      => 'Incheckning',
            'Check out'     => 'Utcheckning',
            'Lead guest'    => 'Huvudgäst',
            'Occupancy'     => 'Beläggning',
            'adult'         => ['vuxna', 'vuxen'],
            //'children'                       => '',
            //            'Special request' => 'Forespørsler',
            'Total price'     => 'Totalpris',
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy'   => 'Avbokningspolicy',
            'Free Cancellation'     => 'Gratis avbokning',
            'Important Information' => 'Viktig information',
        ],
        "uk" => [
            'Hi'            => ['Вітаємо,'],
            'booking ID is' => ['Ваш номер бронювання -'],
            'Reservation'   => 'Бронювання',
            'room'          => 'номер',
            'Room type'     => 'Тип номера',
            'Check in'      => 'Заїзд',
            'Check out'     => 'Виїзд',
            'Lead guest'    => 'Головний гість',
            'Occupancy'     => 'Кількість гостей',
            'adult'         => ["дорослих", "дорослий"],
            'children'      => 'дитина',
            //            'Special request' => 'Forespørsler',
            'Total price'     => 'Загальні витрати',
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy' => 'Правила відміни замовлень',
            'Free Cancellation'   => 'Безкоштовне Скасування',
            // 'Important Information' => '',
        ],
        "ro" => [
            'Hi'            => ['Bună'],
            'booking ID is' => ['ID-ul rezervării dvs. este'],
            'Reservation'   => 'Rezervare',
            'room'          => 'cameră',
            'Room type'     => 'Tipul camerei',
            'Check in'      => 'Check-in',
            'Check out'     => 'Check-out',
            'Lead guest'    => 'Oaspete principal',
            'Occupancy'     => 'Grad de ocupare',
            'adult'         => ["adult", 'adulți'],
            'children'      => ["copii", "copil"],
            //            'Special request' => 'Forespørsler',
            'Total price'     => 'Cost Total',
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy' => 'Politica de Anulare',
            'Free Cancellation'   => 'Anulare gratuită',
            // 'Important Information' => '',
        ],
        "tr" => [
            'Hi'            => ['Merhaba,'],
            'booking ID is' => ['Rezervasyon numaranız:'],
            'Reservation'   => 'Rezervasyon',
            'room'          => 'oda',
            'Room type'     => 'Oda tipi',
            'Check in'      => 'Giriş',
            'Check out'     => 'Çıkış',
            'Lead guest'    => 'Ana konuk',
            'Occupancy'     => 'Kişi sayısı',
            'adult'         => ["yetişkin"],
            'children'      => ["çocuk"],
            //            'Special request' => 'Forespørsler',
            'Total price'     => 'Toplam Ücret',
            //            'Cancellation and Change policy' => 'Termini di Cancellazione e Modifica',
            'Cancellation Policy'   => 'İptal Politikası',
            'Free Cancellation'     => 'Anulare gratuită',
            'Important Information' => 'Önemli Bilgi',
        ],
    ];

    public function parseEmail(Email $email): void
    {
        $xpath = "//tr/*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Check in'), true)}] ]"
            . "/ancestor::*[{$this->contains($this->t('Total price'))}][1]";
        $roots = $this->http->XPath->query($xpath);
        // $this->logger->debug('$xpath ='.print_r( $xpath,true));

        $root = $roots->length > 0 ? $roots->item(0) : null;

        $h = $email->add()->hotel();

        // General

        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('booking ID is'))}]", $root, true, "/{$this->opt($this->t('booking ID is'))}\s*(\d+)/u");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('booking ID is'))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('booking ID is'))}\s*(\d{5,})\b/u");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Occupancy'), true)}]/preceding::text()[{$this->contains($this->t('booking ID is'))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('booking ID is'))}\s*(\d{5,})(?:\b|이며|입니)/u");
        }

        // if (empty($confirmation)) {
        //     $confirmation = $this->http->FindNodes("preceding::text()[{$this->contains($this->t('booking ID is'))}]", $root, "/{$this->opt($this->t('booking ID is'))}\s*(\d{5,})\b/u")[0];
        // }

        $h->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Lead guest'), true)}]/following::text()[normalize-space()][1]", $root);

        if (empty($traveller)) {
            $traveller = str_replace(',', '', $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation'), true)}]/preceding::text()[{$this->starts($this->t('Hi'))}][1]", $root, true, "/{$this->opt($this->t('Hi'))}\s*(\D+)/u"));
        }

        if (empty($traveller)) {
            $traveller = str_replace(',', '', $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation'), true)}]/preceding::text()[{$this->contains($this->t('Hi'))}][1]", $root, true, "/(\D+)\s*{$this->opt($this->t('Hi'))}/u"));
        }

        $h->general()
            ->traveller($traveller, true);

        // if (empty($traveller)) {
        //     $traveller = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Lead guest'), true)}][1]/following::text()[normalize-space()][1]", $root);
        // }

        $cancellation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancellation and Change policy'))}]/following::text()[normalize-space()][1]", $root);

        if (empty($cancellation)) {
            $rows = [];
            $nodes = $this->http->XPath->query("descendant::tr[{$this->eq($this->t('Cancellation Policy'))}][following-sibling::tr[.//*/@style[contains(., 'border-left') and contains(., 'solid')]]]/following-sibling::tr[normalize-space()]", $root);

            $tableEndI = 0;

            foreach ($nodes as $i => $node) {
                $foundTable = false;

                foreach ($this->http->XPath->query(".//*[@style[contains(., 'border-left') and contains(., 'solid')]]", $node) as $sNode) {
                    $v = implode(': ', $this->http->FindNodes(".//tr[not(.//tr) and normalize-space()]", $sNode));

                    if (!empty($v)) {
                        $tableEndI = $i;
                        $foundTable = true;
                        $rows[] = $v;
                    }
                }

                if ($foundTable === true) {
                    continue;
                }

                if (($tableEndI + 1 === $i && $i + 1 !== $nodes->length)
                    || $this->http->FindSingleNode("*[{$this->contains($this->t('Important Information'))}]", $node)
                    || $this->http->FindSingleNode("descendant-or-self::*/@id[normalize-space() = 'important_information_title_text']", $node)) {
                    continue;
                }

                $rows[] = $this->http->FindSingleNode(".", $node);
            }

            $cancellation = implode(". ", array_map(function ($v) {return trim($v, '.'); }, array_unique($rows)));
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Free Cancellation'))}]/ancestor::div[1]", null, true, "/^\s*{$this->opt($this->t('Free Cancellation'))}.+/su");

            if (strlen($cancellation) > 2000) {
                $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Free Cancellation'))}]/ancestor::tr[1]", null, true, "/^\s*{$this->opt($this->t('Free Cancellation'))}.+/su");
            }
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1][{$this->eq($this->t('Important Information'))}]/following-sibling::tr[normalize-space()][1]");
        }

        $h->general()
            ->cancellation($cancellation, true, true);

        $address = $this->http->FindSingleNode("descendant::img[contains(@src, 'star') or contains(@alt, 'star') or contains(@alt,'property image')]"
                . "/preceding::tr[ following-sibling::tr[normalize-space()] ][1]"
                . "/following-sibling::tr[normalize-space()]/descendant::tr[ count(*[.//*])=2 and *[.//*][1][normalize-space()='' and descendant::img] and *[.//*][2][normalize-space()] ]"
                . "/*[.//*][2]/descendant::*[ tr[2] ][1]/tr[normalize-space()][1]", $root) // it-77475924.eml
            ?? $this->http->FindSingleNode("descendant::img[contains(@alt,'property image')]/ancestor::tr[1][ count(*) < 4 and count(*[normalize-space()]) = 1 and *[1][normalize-space()='' and descendant::img] ]"
                . "/*[normalize-space()][1]/descendant::*[ tr[2] ][1]/tr[normalize-space()][1]", $root)
            ?? $this->http->FindSingleNode("descendant::img[contains(@src, 'star') or contains(@alt, 'star') or contains(@alt,'property image')]/following::text()[normalize-space()][1]", $root)
        ;

        $h->hotel()
            ->name(str_replace(['{', '}'], ['[', ']'],
                $this->http->FindSingleNode("descendant::img[contains(@src,'star') or contains(@alt,'star') or contains(@alt,'property image')]/preceding::text()[normalize-space()][1]/ancestor::table[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root)))
            ->address($address);

        $checkIn = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check in'), true)}]/following::text()[normalize-space()][1]", $root);
        $checkInTime = $this->http->FindSingleNode("descendant::*[ tr[1][{$this->eq($this->t('Check in'), true)}] ]/tr[3]", $root);

        if (empty($checkInTime)) {
            $checkInTime = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check in'), true)}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root);
        }

        if ($checkIn && $checkInTime) {
            // it-77475924.eml
            $checkIn .= ' ' . $checkInTime;
        }

        $checkOut = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check out'), true)}]/following::text()[normalize-space()][1]", $root);
        $checkOutTime = $this->http->FindSingleNode("descendant::*[ tr[1][{$this->eq($this->t('Check out'), true)}] ]/tr[3]", $root);

        if (empty($checkOutTime)) {
            $checkOutTime = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Check out'), true)}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root);
        }

        if ($checkOut && $checkOutTime) {
            // it-77475924.eml
            $checkOut .= ' ' . $checkOutTime;
        }

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $node = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Reservation'), true)}]/following::text()[normalize-space()][1]", $root);

        // if (empty($node)) {
        //     $node = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Reservation'), true)}][1]/following::text()[normalize-space()][1]", $root);
        // }

        if (preg_match("/\b(\d+)\s*{$this->opt($this->t('room'))}/u", $node, $m)
            || preg_match("/(?:^|,)\s*{$this->opt($this->t('room'))} (\d+)/u", $node, $m)
        ) {
            $rooms = $m[1];
        }

        if (!empty($rooms)) {
            $h->booked()
                ->rooms(is_numeric($rooms ?? null) ? $rooms : null);
        }

        $node = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Occupancy'), true)}]/following::text()[normalize-space()][1]", $root);

        // if (empty($node)) {
        //     $node = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Occupancy'), true)}]/following::text()[normalize-space()][1]", $root);
        // }

        if (preg_match("/(\d+)\s*{$this->opt($this->t('adult'))}/u", $node, $m)
            || preg_match("/(?:^|,)\s*{$this->opt($this->t('adult'))}[:]?\s*(\d+)/u", $node, $m)
        ) {
            $guests = $m[1];
        }

        $kids = null;

        if (preg_match("/(\d+)\s*{$this->opt($this->t('children'))}/", $node, $m)
            || preg_match("/(?:^|,)\s*{$this->opt($this->t('children'))}[:]?\s*(\d+)/", $node, $m)
            || preg_match("/\s*{$this->opt($this->t('children'))}[:]?\s*(\d+)/", $node, $m)
        ) {
            $kids = $m[1];
        }

        if (!empty($guests)) {
            $h->booked()
                ->guests(is_numeric($guests ?? null) ? $guests : null);
        }

        if ($kids !== null) {
            $h->booked()
                ->kids(is_numeric($kids ?? null) ? $kids : null);
        }

        $roomType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room type'))}]/following::text()[normalize-space()][1]", $root);

        if (empty($roomType)) {
            $roomType = trim($this->http->FindSingleNode("descendant::*[" . $this->contains($this->t('Total price')) . "]/ancestor::tr[1]/preceding-sibling::tr[string-length(normalize-space(./td[2]))>2][last()]/td[1]", $root),
                ': ');
        }

        if (empty($roomType)) {
            $roomType = trim($this->http->FindSingleNode("following::text()[{$this->eq($this->t('Room type'))}]/following::text()[normalize-space()][1]", $root),
                ': ');
        }

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total price'), true)}]";

        $totalPrice = $this->http->FindSingleNode("descendant::tr[{$xpathTotalPrice}]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Total price'))}]/following-sibling::*[normalize-space()][last()][not(contains(.,'*'))]", $root)
            ?? $this->http->FindSingleNode("descendant::tr/*/*[{$this->eq($this->t('Total price'))}]/following-sibling::*[normalize-space()][last()][not(contains(.,'*'))]", $root)
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/descendant::td[contains(.,',') or contains(.,'.')]", $root)
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // EUR 87.98    |    EUR 307,45    |    VND 3.118.497    |    PHP 5,109.60
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

            if ($this->lang === 'vi') {
                $matches['amount'] = str_replace('.', '', $matches['amount']);
            }

            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $discountAmounts = [];
            $feeRows = $this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and following-sibling::tr[{$xpathTotalPrice}] ]", $root);

            foreach ($feeRows as $i => $feeRow) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $feeValue = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*-[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    // THB -600.02
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);

                    continue;
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    if ($i === 0) {
                        $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
                    } else {
                        $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }

            if (count($discountAmounts) > 0) {
                $h->price()->discount(array_sum($discountAmounts));
            }
        }

        $this->detectDeadLine($h, $h->getCancellation());
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length > 0) {
            if ($this->assignLang()) {
                return true;
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

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if (!empty($value[0]) && !empty($value[1])
                    && $this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function starts($field, $addColon = false)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        if ($addColon == true) {
            $field = array_merge($field,
                preg_replace('/([^:])$/', '$1:', $field));
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field, $addColon = false)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        if ($addColon == true) {
            $field = array_merge($field,
                preg_replace('/([^:])$/', '$1:', $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field, bool $removeColon = false, string $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        if ($removeColon) {
            $node = "translate({$node},':：','')";
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date in = ' . print_r($str, true));

        $str = preg_replace("/\b(?:오후|下午)\s*(\d{1,2}:\d{2})/u", '$1 pm', $str);

        // detect time
        if (preg_match("/(?<date>.+)[(（](?<time>.+)[)）]\s*$/u", $str, $m)
            && preg_match("/^\s*\D*(\d{1,2}[:\.]\d{2}(?: ?[ap]m)?)\D*\s*$/iu", $m['time'], $time)
        ) {
            $str = trim($m['date']);
            $time = str_replace('.', ':', trim($time[1]));
        } elseif (
            preg_match("/(?<date>.+),\s*(?<time>\d{1,2}:\d{2}(?: [ap]m)?)\s*$/u", $str, $m)
            || preg_match("/(?<date>.+\))\s*(?<time>\d{1,2}:\d{2}(?: [ap]m)?)\s*$/u", $str, $m)
            || preg_match("/(?<date>.+日)\s+(?<time>\d{1,2}:\d{2}(?: [ap]m)?)\D*\s*$/u", $str, $m)
        ) {
            $str = trim($m['date']);
            $time = trim($m['time']);
        }
        // $this->logger->debug('$date time = ' . print_r($time ?? '', true));
        // $this->logger->debug('$date without time = ' . print_r($str, true));

        $in = [
            //1: Sunday, September 6, 2020; terça-feira outubro 8, 2024
            "/^\s*(?:[-[:alpha:]\p{Thai}]+,?)?\s*([[:alpha:]\p{Thai}]+)\s*(\d{1,2}),\s*(\d{4})\s*$/iu",
            //2: Wednesday, 9 December, 2020    Sonntag, 30. Mai 2021    domingo 30 de mayo de 2021    п'ятниця, 23 грудня 2022 р.; Montag, 16 Okt. 2023
            "/^\s*[-[:alpha:] ']+[,\s]+(\d{1,2})\.?(?:\s*|\s+de\s+)([[:alpha:]]+)[,\.]?(?:\s*|\s+de\s+)(\d{4})\s*\D{0,5}\s*$/u",
            //3: Thứ năm, ngày 15 tháng mười năm 2020
            "/^[-[:alpha:] ]+,\s*[[:alpha:]]+\s*(\d{1,2})\s*([-[:alpha:] ]+)\s+năm\s+(\d{4})\s*$/u",
            //4: 2020年10月1日 星期四    2020년 11월 27일 금요일
            "/^\s*(\d{4})\s*[[:alpha:]]\s*(\d{1,2})\s*[[:alpha:]]\s*(\d{1,2})\s*[[:alpha:]]\D*$/u",
            //5: 17 марта 2021 г.    11. august 2021    07 มิ.ย. เวลา 2021;   04 يونيو, 2021;
            "/^\s*(\d{1,2})\.?\s+([[:alpha:]]+|\S{1,2}\.\S{1,2}\.)(?:,\s*|\s+|\s+เวลา\s+)(\d{4})\s*\D*\s*$/u",
            //6: วันศุกร์ที่ 28 พฤษภาคม ค.ศ. 2021    วันศุกร์ที่ 28 พฤษภาคม 2021
            "/^[^\d\s,.]{2,}[,.\s]*(\d{1,2})[,.\s]*([^\d\s,.]{3,})(?:\s+[^\d\s,.]\.?\s*[^\d\s,.]\.?)?\s+(\d{4})\s*$/u",
            //7: 26.5.2021
            "/^\s*(\d{1,2})\.(\d{1})\.(\d{4})\s*$/u",
            //8: Pirmadienis, 2022, birželis 6
            "/^\s*[-[:alpha:]]+\s*,\s*(\d{4})\s*,\s*([[:alpha:]]+)\s*(\d{1,2})\s*$/u",
            //9: 월요일, 2월 19, 2024    土曜日, 8月 17, 2024
            "/^\s*[[:alpha:]]+\s*,\s*(\d{1,2})\s*[월月]\s*(\d{1,2})\s*,\s*(\d{4})\s*$/u",
            //10: 土曜日 2024年10月12日; 월요일 2024년 11월 04일
            "/^\s*[[:alpha:]]+\s+(\d{4})\s*[年년]\s*(\d{1,2})\s*[月월]\s*(\d{1,2})\s*[日일]\s*$/u",
            // 11: Thứ Sáu tháng 12 6, 2024
            "/^\s*[[:alpha:] ]+\s+tháng\s+(\d{1,2})\s+(\d{1,2})\s*,\s*(\d{4})\s*$/u",
        ];

        $out = [
            // 1
            "$2 $1 $3",
            // 2
            "$1 $2 $3",
            // 3
            "$1 $2 $3",
            // 4
            "$1-$2-$3",
            // 5
            "$1 $2 $3",
            // 6
            "$1 $2 $3",
            // 7
            "$1.0$2.$3",
            // 8
            "$3 $2 $1",
            // 9
            '$1/$2/$3',
            // 10
            '$1-$2-$3',
            // 11
            '$3-$1-$2',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+(.+?)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                foreach (['en', 'es', 'pt', 'nl'] as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        // $this->logger->debug('$date after replace = ' . print_r($str, true));

        $date = strtotime($str);

        if (!empty($date) && !empty($time)) {
            $date = strtotime($time, $date);
        }

        return $date;
    }

    private function detectDeadLine(Hotel $h, $cancellationText): void
    {
        if (empty($h->getCheckInDate())) {
            return;
        }
        // $this->logger->debug('$cancellationText = '.print_r( $cancellationText,true));

        $freeCancellation = '';
        $values = $this->http->FindNodes(".//*[@style[contains(., 'border-left:') and contains(., 'solid')]][.//*[{$this->eq($this->t("Free Cancellation"))}]]/descendant::tr[not(.//tr) and normalize-space()]");

        if (count($values) === 2 && in_array($values[0], (array) $this->t("Free Cancellation"))) {
            $freeCancellation = $values[1];
        }

        if (!empty($freeCancellation)) {
            $date = null;

            $this->logger->debug('$freeCancellation = ' . print_r($freeCancellation, true));

            if (
                // ไม่เกิน 07 มิ.ย. เวลา 23:59 น. (เวลาภูเก็ต); (\x{0E00}-\x{0E7F} - симводы тайского языка)
                // Fino al 18 dic alle 23.59 (orario di Bangkok)
                // Jusqu'au 03 juil., 23:59 - Heure locale de Ollantaytambo
                // До 27 мар (23:59, часовая зона: Рим)
                preg_match("/^\D*\b(?<day>\d{1,2})\b\s*(?<month>[[:alpha:]\x{0E00}-\x{0E7F}\.]{1,20})[,.]?\s*(?:\bเวลา|\balle|à|\()?\s*(?<timeH>\d{1,2})[:.](?<timeM>\d{2})(?<time2> ?[ap]m| ?ก่อนเที่ยง| *หลังเที่ยง)?\b/iu", $freeCancellation, $m)
                // Jusqu'au 23:59 à 9 octobre, heure de Istanbul
                // Until 23:59 19 February Bangkok time
                // Até às 23:59 de 06 jul (hora de Guarulhos)
                // Até as 11:59 PM de 01 dez (hora de Buenos Aires)
                // Dal 10:39 alle 7 ottobre orario di Palawan
                // До 23:59 (15 ноября, часовая зона: Специя)
                // Od 12:00dop. 18 října místního času v destinaci Praha
                // Hasta 11:59p. m. 25 septiembre hora de París
                || preg_match("/^\D*\b(?<time>\d{1,2}:\d{2}(?:\s*[ap]\.? ?m\.?|\s*odp\.|dop\.|nachm\.|vorm\.|da tarde|da manhã|em|fm)?)\s*(?: de | à | alle | \()?\s*(?<day>\d{1,2})\b\s*(?<month>[[:alpha:]]+)\W/iu", $freeCancellation, $m)
                // Até às 7 fevereiro de 23:59 (hora de Newburgh (NY))
                // Até as 21 novembro de 11:59PM (hora de Praga)
                // Até às 20 abril de 11:59da tarde (hora de Londres)
                // A partir de 10 noviembre 11:18p. m. hora de Tokio
                // Bis 24 Dezember 11:59nachm. Uhr (Ortszeit in Udon Thani)
                || preg_match("/^\D*\b(?<day>\d{1,2})\s+(?<month>[[:alpha:]]+)(?: de)? (?<time>\d{1,2}:\d{2}(?:\s*(?:[ap]\.? ?m\.?|da tarde|da manhã|nachm\.|vorm\.))?)\W/iu", $freeCancellation, $m)
                // Until 11:59PM February 18th (Kanchanaburi time)
                || preg_match("/^\D*\b(?<time>\d{1,2}:\d{2}(?:\s*[ap]\.?m\.?)?)\s+(?<month>[[:alpha:]]+) (?<day>\d{1,2})(?:th|st|nd|rd)?(?:\s+|\()/iu", $freeCancellation, $m)
                // Until March 19th 11:59pm (Bangkok time)
                || preg_match("/^\D*\s+(?<month>[[:alpha:]]+) (?<day>\d{1,2})(?:th|st|nd|rd)?\s+(?<time>\d{1,2}:\d{2}(?:\s*[ap]\.?m\.?)?)(?:\s+|\()/iu", $freeCancellation, $m)
            ) {
                if ($this->lang !== 'th') {
                    $m['month'] = trim($m['month'], '.');
                }

                foreach ([$this->lang, 'en', 'es', 'nl'] as $lang) {
                    $monthL = MonthTranslate::translate($m['month'], $lang);

                    if (!empty($monthL)) {
                        $m['month'] = $monthL;
                    }
                }

                $date = EmailDateHelper::parseDateRelative($m['day'] . ' ' . $m['month'], $h->getCheckInDate(), false, '%D% %Y%');

                if (empty($m['time'])) {
                    $m['time'] = $m['timeH'] . ':' . $m['timeM'] . ($m['time2'] ?? '');
                }
                $m['time'] = preg_replace('/([ap])\. *(m)\./', '$1$2', $m['time']);
                $m['time'] = str_replace(['dop.', 'ก่อนเที่ยง', 'vorm.', 'da manhã', 'a. m.', 'em'], 'AM', $m['time']);
                $m['time'] = str_replace(['odp.', 'หลังเที่ยง', 'nachm.', 'da tarde', 'p. m.', 'fm'], 'PM', $m['time']);
                // a.m. -> am
                $m['time'] = str_replace('.', '', $m['time']);

                if (!empty($date)) {
                    $date = strtotime($m['time'], $date);
                }
            } elseif (
                //대구 현지 시간으로 2월 05일 오후 11:59까지
                // 방콕 현지 시간으로 10월 31일 23:59까지
                preg_match("/^\D*(?<month>\d{1,2}) *[月월] *(?<day>\d{1,2}) *[日일]\s*(?<time2>\D+)(?<time>\d{1,2}:\d{2}(?: ?[ap]m)?)\D+/iu", $freeCancellation, $m)
                // 镰仓时间11月 24日03:34上午起
                || preg_match("/^\D*(?<month>\d{1,2}) *[月월] *(?<day>\d{1,2}) *[日일]\s*(?<time>\d{1,2}:\d{2}(?: ?[ap]m)?)(?<time2>午前|上午|오전|午後|下午|오후)?\D+/iu", $freeCancellation, $m)
                // 서울 현지 시간으로 22:42 9월 06일부터
                // 东京时间15:2710月 7日起
                // 福岡時間7:09 10月 4日起
                // 從大阪時間11:17下午 10月 14日起
                || preg_match("/^\D*(?<time>\d{1,2}:\d{2})(?<time2>[[:alpha:]]{2})? ?(?<month>\d{1,2})\s*[月월]\s*(?<day>\d{1,2})\s*[日일]/iu", $freeCancellation, $m)
                // Cho đến ngày 07 thg 6 23:59 Mumbai giờ
                // Đến 4 tháng 12 11:59CH theo giờ Đà Lạt
                || preg_match("/^\D*(?<day>\d{1,2}) *(?:thg|tháng) *(?<month>\d{1,2})\D*(?<time>\d{1,2}:\d{2} ?(?:Cb|SA\b)?)\D+/iu", $freeCancellation, $m)
            ) {
                if (!empty($m['time2'])) {
                    $m['time2'] = str_replace(['午前', '上午', '오전', 'SA'], 'AM', $m['time2']);
                    $m['time2'] = str_replace(['午後', '下午', '오후', 'CH'], 'PM', $m['time2']);
                    $m['time'] = $m['time'] . ' ' . $m['time2'];
                }
                $m['month'] = str_pad($m['month'], 2, '0', STR_PAD_LEFT);
                $m['day'] = str_pad($m['day'], 2, '0', STR_PAD_LEFT);
                $date = EmailDateHelper::parseDateRelative($m['day'] . '.' . $m['month'], $h->getCheckInDate(), false, '%D%.%Y%');

                if (!empty($date)) {
                    $date = strtotime($m['time'], $date);
                }
            }

            if (empty($date)) {
                $this->logger->error('not parsing deadline: ' . $freeCancellation);
            } else {
                $h->booked()->deadline($date);

                return;
            }
        }

        if (empty($cancellationText)) {
            return;
        }

        // without normalization
        if (preg_match("/You (?i)can cancell? until (?<day>\d{1,2})\s*(?<month>[-[:alpha:]]{3,})\s*(?<year>\d{4}) and pay nothing/", $cancellationText, $m)
            || preg_match("/Free (?i)Cancell?ation\:? (?:Until|From) (?<day>\d{1,2}) (?<month>[-[:alpha:]]{3,}) (?<time>\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?).*time/", $cancellationText, $m)
            || preg_match("/无风险预订——在(?<year>\d{4})年(?<month>\d{1,2})月(?<day>\d{1,2})日当日及以前取消均不收费/u", $cancellationText, $m)
            || preg_match("/彈性訂房！(?<year>\d{4})年(?<month>\d{1,2})月(?<day>\d{1,2})日前\(含當日\)/u", $cancellationText, $m)
            || preg_match("/無風險預訂！(?<year>\d{4})年(?<month>\d{1,2})月(?<day>\d{1,2})日 星期三/u", $cancellationText, $m)
        ) {
            if (empty($m['year'])) {
                $deadline = EmailDateHelper::parseDateRelative($m['day'] . '.' . $m['month'], $h->getCheckInDate(), false, '%D%.%Y%');
            } else {
                $deadline = strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year']);
            }

            if (!empty($m['time'])) {
                $deadline = strtotime($m['time'], $deadline);
            }
            $h->booked()->deadline($deadline);
        }

        // with normalization
        if (
               preg_match("#You can cancel until (?<month>\w+)\s*(?<day>\d+)\,\s*(?<year>\d{4}) and pay nothing#i", $cancellationText, $m)
            || ($lang = 'es' && preg_match("#Reserva sin riesgos! Puedes cancelar hasta el (?<day>\d{1,2}) de (?<month>\w+) de (?<year>\d{4}) sin pagar nada\.#u", $cancellationText, $m))
               // id
            || ($lang = 'id' && preg_match("#Pesan tanpa risiko! Pembatalan gratis jika dilakukan paling lambat (?<month>\w+) (?<day>\d{1,2}), (?<year>\d{4})\b#u", $cancellationText, $m))
               // fr
            || ($lang = 'fr' && preg_match("#Réservation sans risque ! Vous pouvez annuler jusqu'au (?<day>\d{1,2}) (?<month>\w+) (?<year>\d{4}) et vous n'aurez rien à payer !#u", $cancellationText, $m))
        ) {
            foreach ([$this->lang, $lang] as $localLang) {
                $monthL = MonthTranslate::translate($m['month'], $localLang);

                if (!empty($monthL)) {
                    $m['month'] = $monthL;
                }
            }
            $h->booked()->deadline($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year']));
        }

        // nonRefundable
        if (
               preg_match("#This booking is Non-Refundable and cannot be amended or modified#i", $cancellationText, $m)
            || preg_match("#Esta reserva não é reembolsável e não pode ser alterada ou modificada\.#i", $cancellationText, $m)
            || preg_match("#Оплата этого бронирования не возвращается\. Оно не может быть дополнено или изменено\.#iu", $cancellationText, $m)
            || preg_match("#Tato rezervace je nevratná a nemůže být změněna nebo upravena\.#iu", $cancellationText, $m) // cs
            || preg_match("#此预订无法申请退款，且不能进行修改。#iu", $cancellationText, $m)
            || preg_match("#이 예약은 환불이 불가하며 수정하거나 변경할 수 없습니다\.#iu", $cancellationText, $m)
            || preg_match("#זו אינה ניתנת לביטול או שינוי ולכן לא יינתן עליה החזר כספי במקרה של ביטול\.#iu", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }
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
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
