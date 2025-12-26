<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: split method normalizeDate() on methods normalizeDate() and normalizeTime()

class ReservationConfirmation2018 extends \TAccountChecker
{
    public $mailFiles = "marriott/it-100381305.eml, marriott/it-100856885.eml, marriott/it-13275807.eml, marriott/it-13277934.eml, marriott/it-197582229.eml, marriott/it-27194432.eml, marriott/it-29979511.eml, marriott/it-31493549.eml, marriott/it-31574038.eml, marriott/it-33638014.eml, marriott/it-33750514.eml, marriott/it-33750562.eml, marriott/it-34621682.eml, marriott/it-41189694.eml, marriott/it-46256029.eml, marriott/it-57949127.eml, marriott/it-876681633.eml"; // +1 bcdtravel(html)[pt]
    public $lang = '';
    public static $dictionary = [
        'en' => [
            "CONTACT US"         => ["CONTACT US", "Contact Us"],
            "Thanks for booking" => ["Thanks for booking", "Thank you for booking"],
            //			"My Account" => ["My Account", "Member Benefits", "My Marriott Rewards Account", "My SPG Account", "My Ritz-Carlton Rewards Account"],
            "Plan your stay," => ["Plan your stay,", "Pursue your passion,", "Bring a sense of occasion to your everyday,", "Plan with ease,", 'Thank you for your booking,', 'Thank you for booking directly with us,'],
            "Check-In:"       => ["Check-In:", "Check In:"],
            "Check-Out:"      => ["Check-Out:", "Check Out:"],
            "Room1"           => "Room",
            "Room2"           => "Room",
            "Kid"             => ["Kid", "Child"],
            "fees"            => "Service charge",
            "Number of rooms" => ["Number of rooms", "Number of Rooms:"],
            "Guests per room" => ['Guests per room', 'Number of Guests:'],
            "Adult"           => ["Adult", "Guest"],
            "up to"           => ['UP TO', 'up to', 'Up To'],
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["reservation is cancelled", "Reservation Cancellation"],
        ],
        'es' => [
            "CONTACT US"        => ["CONTACTO", "Contacto"],
            "Thanks for booking"=> ["Gracias por reservar", "Gracias por su reserva", "Gracias por reservar con nosotros"],
            //			"Plan your stay,"=>"",
            "Confirmation Number:"             => "Número de confirmación:",
            "Check-In:"                        => ["Llegada:", "Check-In:"],
            "Check-Out:"                       => ["Salida:", "Check-Out:"],
            "Number of rooms"                  => "Cantidad de habitaciones",
            "Room1"                            => ["habitación", "habitaciones"],
            "Room2"                            => ["habitación", "habitaciones"],
            "Guests per room"                  => "Huéspedes por habitación",
            "Adult"                            => "adulto",
            "Kid"                              => "niño",
            "Room Type"                        => "Tipo de habitación",
            "Summary Of Charges"               => "Detalle de los cargos",
            "at"                               => "a",
            " per night per room"              => " por habitación, por noche",
            "Total for Stay (all rooms)"       => "Total por estancia (todas las habitaciones)",
            "Estimated Government Taxes & Fees"=> "Impuestos y cargos del gobierno calculados",
            "fees"                             => ["Tarifa de resort", "Tasas de destino", "Cargo por persona adicional"],
            "Cancellation Policy"              => ["Política de cancelación"],
            "Account"                          => ["Número de cuenta", "Account"],
            "My Account"                       => ["Mi cuenta de Marriott Rewards", "Mi cuenta de SPG", "Mi cuenta", "My Account"],
            "Status"                           => ["Status", "Estatus"],
            "Points"                           => ["Points", "Puntos"],
            "Total Points Redeemed"            => "Total de puntos canjeados",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["reserva está cancelada", "Cancelación de la reserva"],
            "Cancellation Number:"     => ["Número de cancelación:", 'Cancellation Number:'],
            "Confirmation Number"      => "Número de confirmación",
            "Rate Information"         => ["Detalles sobre tarifas", "Rate Information"],
        ],
        'pt' => [
            "CONTACT US"         => ["Entre em contato", "ENTRE EM CONTATO"],
            "Thanks for booking" => "Obrigado por fazer sua reserva conosco",
            //			"Plan your stay,"=>"",
            "Confirmation Number:"             => ["Nº de confirmação:", "Nº de confirmação"],
            "Check-In:"                        => "Check-in:",
            "Check-Out:"                       => "Check-out:",
            "Number of rooms"                  => "Número de quartos",
            "Room1"                            => "Quarto",
            "Room2"                            => "Quarto",
            "Guests per room"                  => "Hóspedes por quarto",
            "Adult"                            => ["Adulto", "Adultos"],
            "Kid"                              => "Criança",
            "Room Type"                        => "Categoria de quarto",
            "Summary Of Charges"               => ["Total por estada", "Resumo das despesas"],
            "at"                               => "a",
            " per night per room"              => "por diária, por quarto",
            "Total for Stay (all rooms)"       => "Total por estada (todos os quartos)",
            "Estimated Government Taxes & Fees"=> "Estimativa de taxas e impostos governamentais",
            //            "fees" => "",
            "Cancellation Policy"   => "e política de cancelamento",
            "Status"                => ["Status"],
            "Account"               => "Conta",
            "My Account"            => ["Minha conta"],
            "Points"                => ["Pontos"],
            "Total Points Redeemed" => "Total de pontos resgatados",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["Cancelamento da reserva"],
            "Cancellation Number:"     => "Número do cancelamento:",
            "Confirmation Number"      => "Nº de confirmação",
            "Rate Information"         => "Resumo das despesas",
        ],
        'ko' => [
            "CONTACT US"=> ["연락처"],
            //			"Thanks for booking"=>"",
            "Plan your stay,"                  => "직접 예약해주셔서 감사합니다,",
            "Confirmation Number:"             => "확약 번호:",
            "Check-In:"                        => "체크인:",
            "Check-Out:"                       => "체크아웃:",
            "Number of rooms"                  => "객실 수",
            "Room1"                            => "개",
            "Room2"                            => "객실",
            "Guests per room"                  => "객실당 투숙객 수",
            "Adult"                            => "성인",
            "AdultPostfix"                     => "인",
            "Kid"                              => "어린이 ",
            "Room Type"                        => "객실 유형",
            "Summary Of Charges"               => "요금 내역",
            "at"                               => "-",
            " per night per room"              => " 객실별/1박당",
            "Total for Stay (all rooms)"       => "숙박 요금 합계(전 객실)",
            "Estimated Government Taxes & Fees"=> "정부 세금 및 요금 추정액",
            "fees"                             => "봉사료",
            "Cancellation Policy"              => "취소 규정",
            "Account"                          => "계정",
            "Points"                           => "포인트",
            "Status"                           => "등급",
            "My Account"                       => ["내 계정"],
            "Total Points Redeemed"            => "사용한 포인트 합계",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["고객님의 예약이 취소되었습니다", "함께하시기를 기대합니다."],
            "Cancellation Number:"     => "취소 번호:",
            "Confirmation Number"      => "확약 번호",
            "Rate Information"         => "요금 정보",
        ],
        'zh' => [
            "CONTACT US"=> ["연락처"],
            //			"Thanks for booking"=>"",
            "Plan your stay,"     => "직접 예약해주셔서 감사합니다,",
            "Confirmation Number:"=> ["取消号码:", "确认号码:", "確認號碼:"],
            "Check-In:"           => ["登记入住:", "登記入住:"],
            "Check-Out:"          => "退房:",
            "Number of rooms"     => ["客房数量", "Number of rooms"],
            "Room1"               => ["客房", "Room"],
            "Room2"               => ["客房", "Room"],
            "Guests per room"     => ["每间客房宾客人数", "Guests per room"],
            "Adult"               => "成人",
            //			"AdultPostfix"=>"인",
            "Kid"                              => "儿童",
            "Room Type"                        => ["客房类型", "客房類型"],
            "Summary Of Charges"               => "费用摘要",
            "at"                               => ["住宿费用为", "-"],
            " per night per room"              => " 每房每晚",
            "Total for Stay (all rooms)"       => ["总住宿费（所有客房）", "Total for Stay (all rooms)"],
            "Estimated Government Taxes & Fees"=> "预估政府税款和费用",
            "fees"                             => "服务费",
            "Cancellation Policy"              => ["房价信息和取消政策", "房價詳情與取消政策"],
            "Account"                          => "帐户",
            "My Account"                       => ["我的帐户"],
            "Total Points Redeemed"            => "已兑换的积分总额",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["您的预订已取消", "您的預訂已取消", '预订取消'],
            "Cancellation Number:"     => ["取消号码:", "取消號碼:"],
            "Confirmation Number"      => ["确认号码", "確認號碼"],
            "Rate Information"         => ["价目信息", "房價資訊"],
            'Points'                   => '积分',
            'Status'                   => '会籍',
        ],
        'ja' => [
            "CONTACT US"=> ["お問い合わせ"],
            //			"Thanks for booking"=>"",
            //			"Plan your stay,"=>"",
            "Confirmation Number:"=> "予約確認番号:",
            "Check-In:"           => "チェックイン:",
            "Check-Out:"          => "チェックアウト:",
            "Number of rooms"     => "客室数",
            "Room1"               => "客室",
            "Room2"               => "部屋",
            "Guests per room"     => "1室あたりの宿泊者数",
            "Adult"               => "大人",
            "AdultPostfix"        => "人",
            // "Kid"=>"",
            "Room Type"                        => "部屋タイプ",
            "Summary Of Charges"               => "料金の概要",
            "at"                               => "a",
            " per night per room"              => " 1泊1室あたり",
            "Total for Stay (all rooms)"       => "ご滞在費総額 (全室)",
            "Estimated Government Taxes & Fees"=> "諸税諸費見積もり",
            "fees"                             => "サービス料金",
            "Cancellation Policy"              => "料金の詳細とキャンセルポリシー",
            "Account"                          => "アカウント",
            "My Account"                       => ["マリオット リワード マイアカウント", 'マイアカウント'],
            "Points"                           => "ポイント",
            "Status"                           => "ステータス",
            "Total Points Redeemed"            => "交換されたポイント合計:",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["ご予約はキャンセルされました", 'ご予約がキャンセルされました', 'ご予約のキャンセル'],
            "Cancellation Number:"     => "キャンセル番号:",
            "Confirmation Number"      => "予約確認番号",
            "Rate Information"         => "料金情報",
        ],
        'de' => [
            "CONTACT US"                       => "Kontakt",
            "Thanks for booking"               => "Vielen Dank für Ihre Buchung",
            "Plan your stay,"                  => "Vielen Dank für Ihre Buchung,",
            "Confirmation Number:"             => "Bestätigungsnummer:",
            "Check-In:"                        => "Check-In:",
            "Check-Out:"                       => "Check-Out:",
            "Number of rooms"                  => "Anzahl an Zimmern",
            "Room1"                            => "Zimmer",
            "Room2"                            => "Zimmer",
            "Guests per room"                  => "Gäste pro Zimmer",
            "Adult"                            => "Erwachsene",
            "Kid"                              => "Kind",
            "Room Type"                        => "Zimmertyp",
            "Summary Of Charges"               => "Gesamtkosten",
            "at"                               => ["in", "zu"],
            " per night per room"              => " pro Zimmer, pro Nacht",
            "Total for Stay (all rooms)"       => "Insgesamt für Aufenthalt (alle Zimmer)",
            "Estimated Government Taxes & Fees"=> "Geschätzte Steuern und Abgaben",
            "fees"                             => ["Servicegebühren", "Staatskosten - Restaurationsgebühr", "Kongress- und Tourismussteuer", 'Gebühr für zusätzliche Person'],
            "Cancellation Policy"              => "Stornierungsbedingungen",
            "Account"                          => "Konto",
            "Points"                           => "Punkte",
            "Status"                           => "Status",
            "My Account"                       => ["Mein Konto", "Mein Marriott Rewards Konto", "Mein SPG Konto"],
            "Total Points Redeemed"            => "Insgesamt eingelöste Punkte",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["Buchung wurde storniert", "Reservierungsstornierung"],
            "Cancellation Number:"     => "Reservierungsstornierung:",
            "Confirmation Number"      => "Bestätigungsnummer",
            "Rate Information"         => "Preisinformationen",
        ],
        'fr' => [
            "CONTACT US"                       => "NOUS CONTACTER",
            "Thanks for booking"               => "Merci d’avoir réservé avec nous",
            "Plan your stay,"                  => "Merci d’avoir réservé directement auprès de nous,",
            "Confirmation Number:"             => ["Numéro de confirmation:"],
            "Check-In:"                        => "Arrivée:",
            "Check-Out:"                       => "Départ:",
            "Number of rooms"                  => "Nombre de chambre(s)",
            "Room1"                            => "Chambre",
            "Room2"                            => "Chambre",
            "Guests per room"                  => "Occupant(s) par chambre",
            "Adult"                            => "Adulte",
            "Kid"                              => "Enfant",
            "Room Type"                        => "Type de chambre",
            "Summary Of Charges"               => "Résumé des frais",
            "at"                               => "à",
            " per night per room"              => " par chambre et par nuit",
            "Total for Stay (all rooms)"       => "Total pour le séjour (toutes les chambres)",
            "Estimated Government Taxes & Fees"=> "Estimation des taxes gouvernementales et des frais de séjour",
            "fees"                             => ["Frais de service", "Frais de congrès / tourisme"],
            "Cancellation Policy"              => "conditions d’annulation",
            "Account"                          => "Numéro de compte",
            "Status"                           => ["Status", "Statut"],
            "Points"                           => "Points",
            "My Account"                       => ["Mon compte Marriott Rewards", "Avantages pour les membres", "Mon compte"],
            "Total Points Redeemed"            => "Nombre total de points échangés",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["réservation est annulée", 'Annulation de réservation'],
            "Cancellation Number:"     => "Numéro d'annulation:",
            "Confirmation Number"      => "Numéro de confirmation",
            "Rate Information"         => "Tarif",
        ],
        'it' => [
            "CONTACT US"=> "CONTATTI",
            //			"Thanks for booking"=>"",
            "Plan your stay,"                  => "Grazie per la vostra prenotazione,",
            "Confirmation Number:"             => "Numero di conferma:",
            "Check-In:"                        => "Check-in:",
            "Check-Out:"                       => "Check-out:",
            "Number of rooms"                  => "Numero di camere",
            "Room1"                            => "camera",
            "Room2"                            => "Camera",
            "Guests per room"                  => "Ospiti per camera",
            "Adult"                            => "adult",
            "Kid"                              => "Bambin",
            "Room Type"                        => "Tipo di camera",
            "Summary Of Charges"               => "Riepilogo dei costi",
            "at"                               => "a",
            " per night per room"              => " per camera per notte",
            "Total for Stay (all rooms)"       => "Totale per soggiorno (tutte le camere)",
            "Estimated Government Taxes & Fees"=> "Tasse e spese governative stimate",
            "fees"                             => "TakeCare Relief Fund (Opzionale)",
            "Cancellation Policy"              => "termini di cancellazione",
            "Account"                          => "Numero account",
            "Points"                           => "punti",
            "Status"                           => "Stato",
            "My Account"                       => ["Il mio account"],
            //			"Total Points Redeemed" => "",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["prenotazione è annullata"],
            "Cancellation Number:"     => "Numero di cancellazione:",
            "Confirmation Number"      => "Numero di conferma ",
            "Rate Information"         => "Date della prenotazione",
        ],
        'ru' => [
            "CONTACT US"         => "СВЯЗАТЬСЯ С НАМИ",
            "Thanks for booking" => "Благодарим за бронирование",
            //            "Plan your stay,"     => ",",
            "Confirmation Number:"             => "Номер подтверждения:",
            "Check-In:"                        => "Заезд:",
            "Check-Out:"                       => "Выезд:",
            "Number of rooms"                  => "Количество номеров",
            "Room1"                            => "номер",
            "Room2"                            => "Номер",
            "Guests per room"                  => "Гостей в номере",
            "Adult"                            => "взросл",
            "Kid"                              => ["дет", "ребенок"],
            "Room Type"                        => "Тип номера",
            "Summary Of Charges"               => "Перечень расходов",
            "at"                               => "по",
            " per night per room"              => " в сутки за номер",
            "Total for Stay (all rooms)"       => "Всего за пребывание (за все номера)",
            "Estimated Government Taxes & Fees"=> "Приблизительная сумма государственных налогов и сборов",
            //            "fees" => "",
            "Cancellation Policy"   => "правила отмены",
            "Account"               => "Личный кабинет",
            "Points"                => "Баллов",
            "Status"                => "Статус",
            "My Account"            => ["Мой личный кабинет"],
            "Total Points Redeemed" => "Итого использовано баллов",
            // "up to" => "",
            // CANCELLED RESERVATION
            "reservation is cancelled" => ["Ваше бронирование отменено"],
            "Cancellation Number:"     => "Номер отмены бронирования:",
            "Confirmation Number"      => "Номер подтверждения",
            "Rate Information"         => "Подробнее о тарифе",
        ],
        'nl' => [
            "CONTACT US"         => "CONTACT OPNEMEN",
            "Thanks for booking" => "Hartelijk dank voor uw reservering",
            //            "Plan your stay,"     => ",",
            "Confirmation Number:"             => "Bevestigingsnummer:",
            "Check-In:"                        => "Inchecken:",
            "Check-Out:"                       => "Uitchecken:",
            "Number of rooms"                  => "Aantal kamers",
            "Room1"                            => "kamer",
            "Room2"                            => "Kamer",
            "Guests per room"                  => "Gasten per kamer",
            "Adult"                            => "volwassene",
            "Kid"                              => ["kind"],
            "Room Type"                        => "Kamertype",
            "Summary Of Charges"               => "Overzicht van de kosten",
            "at"                               => "voor",
            " per night per room"              => " per nacht per kamer",
            "Total for Stay (all rooms)"       => "Totaal voor verblijf (alle kamers)",
            "Estimated Government Taxes & Fees"=> "Geschatte overheidsbelastingen en -kosten",
            //            "fees" => "",
            "Cancellation Policy"=> "Tariefgegevens en annuleringsvoorwaarden",
            "Account"            => "Account",
            "Points"             => "Punten",
            "Status"             => "Status",
            "My Account"         => ["Mijn account"],
            // "Total Points Redeemed" => "Итого использовано баллов",
            // "up to" => "",
            // CANCELLED RESERVATION
            // "reservation is cancelled" => ["Ваше бронирование отменено"],
            // "Cancellation Number:" => "Номер отмены бронирования:",
            // "Confirmation Number" => "Номер подтверждения",
            // "Rate Information" => "Подробнее о тарифе",
        ],
    ];
    protected $langDetectors = [
        'zh' => ['旅途正在召唤。', '享受无可挑剔的住宿体验。', '，规划您的住宿。', '取消号码:', '取消號碼:', '感謝您通過官方渠道直接預訂。', '感谢您的预订。', '感谢您通过官方渠道直接预订。', '准备好尽情享受舒适。', '轻松规划。', '也是规划住宿的好时机。', '点燃激情。', '为您的日常生活赋予仪式感', '请您准备好前往。', '感谢您预订我们', '登记入住:'],
        'ko' => ['체크인:', '고객님의 예약이 취소되었습니다', '취소 번호:'],
        'es' => ['Llegada:', 'Detalles sobre tarifas', 'Número de confirmación'],
        'de' => ['Bestätigungsnummer:', 'Preisinformationen'],
        'fr' => ['Arrivée:', 'Numéro de confirmation'],
        'ja' => ['チェックアウト:', 'ご予約はキャンセルされました', 'ご予約がキャンセルされました', 'キャンセル番号:'],
        'pt' => ['Obrigado por fazer sua reserva diretamente conosco', 'Obrigado pela sua reserva', 'Obrigado por fazer sua reserva conosco', 'Modificar ou cancelar a reserva', 'Sua reserva está cancelada'],
        'it' => ['Numero di conferma:', 'Riepilogo dei costi', 'Servizi inclusi nella tariffa'],
        'ru' => ['Заезд:', 'Перечень расходов', 'Подробнее о тарифе'],
        'nl' => ['Uitchecken:', 'Overzicht van de kosten'],
        'en' => ['Check-Out:', 'Rate Details', 'Check Out:'], // the last
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?', // 4:19PM  |  2:00 p.m.  |  3pm  |  12 noon
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
    ];

    private $date = null;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, ' By Marriott Reservation') !== false
            || stripos($from, '@res-marriott.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        $subjectPartRe = [
            // en
            'Reservation (?:Confirmation|Cancellation) #',
            //zh
            '的预订确认码 #|酒店 的预订取消 #',
            // ja
            'の予約確認番号|のご予約のキャンセル番号#',
            // de
            'Reservierungsbestätigung #|Reservierungsstornierung #',
            // fr
            'Confirmation de votre réservation n°|Annulation du séjour dans l\'établissement .+, n° ',
            // ru
            'Подтверждение бронирования #|Отмена бронирования #',
            //es
            'Confirmación de la reserva #|Cancelación de la reserva #',
            // pt
            'Confirmação de reserva',
            // ko
            '예약 취소 #|예약 확약 번호',
            // it
            'Conferma della prenotazione n\.|Cancellazione della prenotazione n\.',
            // nl
            'Reserveringsbevestiging nr\.',
        ];

        if (preg_match('/(?:' . implode('|', $subjectPartRe) . ')\s*\d{5,}(?: for | para | für | pour | в |$)/iu', $headers['subject']) > 0) {
            return true;
        }

        $subjects = [
            'Plan for your upcoming stay at',
        ];

        foreach ($subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your Marriott Rewards Point") or contains(normalize-space(.),"your Marriott Rewards base points") or contains(normalize-space(.),"By Marriott Reservation") or contains(.,"@res-marriott.com") or contains(.,".marriott.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"res-marriott.com") or contains(@href,"www.marriott.com")] | //img[contains(@src,".marriott.com/") or contains(@alt,"Marriott")]')->length === 0;
        $condition3 = $this->http->XPath->query('//tr[descendant::img[contains(@src, "marriott") or contains(@alt, "Marriott ")] and descendant::a[contains(normalize-space(.), "Marriott")]]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/Marriott Vacation Club/", $parser->getSubject())
            || $this->http->XPath->query("//td[contains(normalize-space(), 'Marriott Vacation Club')]")->length > 0) {
            // use vacation club only where vacation club points are used
//            $email->setProviderCode('marriottvacationclub');
        }

        if (empty($this->http->Response['body'])) {//it-33638014.eml
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('reservation is cancelled'))}])[1]"))
            && empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check-Out:'))}])[1]"))) {
            $this->parseEmailCancel($email);
        } else {
            $this->parseEmail($email);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

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

    private function parseEmail(Email $email)
    {
        $this->logger->info(__METHOD__);
        $h = $email->add()->hotel();

        $xpathCell = '(self::td or self::th)';
        $xpathFragment1 = "//tr[not(.//tr) and .//text()[" . $this->eq($this->t("CONTACT US")) . "]]/following::table[normalize-space(.)][not(contains(., 'COVID-19'))][1]/descendant::tr[ count(./td)=2 and ./descendant::tr and ./td[1][./descendant::img or ./descendant::text()[contains(normalize-space(.),'Error! Filename not specified.')]]][1]";
        $xpathFragment2 = '/td[2]/descendant::tr[not(.//tr) and normalize-space(.)][1]/following-sibling::tr[normalize-space(.)][1]/descendant::td[not(.//td) and not(contains(normalize-space(.),"Error! Filename not specified.")) and string-length(normalize-space(.))>6]';

        $hotelName = $this->http->FindSingleNode('(' . $xpathFragment1 . '/td[2]/descendant::tr[not(.//tr) and normalize-space(.)][1])[1][preceding::text()]');
        $address = $this->http->FindSingleNode('(' . $xpathFragment1 . $xpathFragment2 . '[1])[1]');
        $phone = $this->http->FindSingleNode('(' . $xpathFragment1 . $xpathFragment2 . '[2])[1]', null, false, "/^({$this->patterns['phone']})$/");

        if (empty($hotelName) && empty($address)) {
            $xpath = '(//text()[' . $this->starts($this->t("Thanks for booking")) . ']/preceding::table[preceding-sibling::table][1][contains(.//a/@href, "marriott.com")]//tr[count(td)=2])[1]/td[2]//*[count(tr)=2 and count(./tr[2]//td[not(.//td) and normalize-space()])=2]';
            $hotelName = $this->http->FindSingleNode($xpath . '/tr[1]');
            $address = $this->http->FindSingleNode('(' . $xpath . '/tr[2]//td[not(.//td) and normalize-space()])[1]');
            $phone = $this->http->FindSingleNode('(' . $xpath . '/tr[2]//td[not(.//td) and normalize-space()])[2]', null, false, "/^({$this->patterns['phone']})$/");
        }

        if (empty($hotelName) && empty($address)) {
            $xpath = "//tr[not(.//tr) and ({$this->contains($this->t('CONTACT US'))})]/following::table[normalize-space(.)][1]/descendant::td[ count(descendant::p[normalize-space(.)])>3 ]";
            $hotelName = $this->http->FindSingleNode($xpath . '/p[1]');
            $address = $this->http->FindSingleNode($xpath . '/p[2]') . $this->http->FindSingleNode($xpath . '/p[3]', null, true, '/(.+) Phone\s*\:/');
            $phone = $this->http->FindSingleNode($xpath . '/p[4]', null, true, "/^({$this->patterns['phone']})$/");
        }

        if (empty($hotelName) && empty($address)) {
            $texts = $this->http->FindNodes("(//tr[not(.//tr) and ({$this->contains($this->t('CONTACT US'))})]/following::table[normalize-space(.)])[1]/descendant::text()[normalize-space()]");

            if (count($texts) == 3 && strlen(preg_replace("#\D+#", '', $texts[2])) > 5 && (
                    stripos($texts[0], 'Sheraton') !== false || stripos($texts[0], 'Courtyard') !== false || stripos($texts[0], 'Ritz-Carlton') !== false
            )) {
                $hotelName = $texts[0];
                $address = $texts[1];
                $phone = $texts[2];
            }
        }

        $xpathImgAddress = "{$this->contains('map_icon', '@src')} or {$this->eq('Address', '@alt')} or ({$this->contains('removed', '@alt')} and {$this->contains('Address', '@alt')})";
        $xpathImgPhone = "{$this->contains('phone_icon', '@src')} or {$this->eq('Telefone', '@alt')} or ({$this->contains('removed', '@alt')} and {$this->contains('Telefone', '@alt')})";

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[{$xpathImgAddress}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[{$xpathImgAddress}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        }

        if (empty(trim($address))) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='{$hotelName}']/following::text()[normalize-space()][1]");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[{$xpathImgPhone}]/ancestor::td[1]/following-sibling::td[1]", null, true, "/({$this->patterns['phone']})/u");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode('//td[contains(normalize-space(),"Call") and contains(normalize-space(),"in the ")]', null, false, "/Call\s*({$this->patterns['phone']})/");
        }

        $re = '/(\<.+\>)/';

        if (preg_match("/http/u", $hotelName)) {
            $hotelName = $this->re("/^(.+)\s*http/u", $hotelName);
        }

        if (preg_match("/http/u", $address)) {
            $address = $this->re("/^(.+)\s*http/u", $address);
        }

        $h->hotel()
            ->name(preg_replace($re, '', $hotelName))
            ->address(preg_replace($re, '', $address))
            ->phone($phone, false, true);

        $conf = $this->http->FindSingleNode("(//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Confirmation Number:"))}])[1]", null, true, "#^[^:]+:\s*([A-Z\d]{5,})$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Confirmation Number:"))}])[1]", null, true, "#\s+([A-Z\d]{5,})$#");
        }

        if (empty($conf) && empty($this->http->FindSingleNode("//*[$xpathCell and not(.//*[$xpathCell]) and {$this->contains($this->t("Confirmation Number:"))}]"))) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($conf);
        }

        $traveller = $this->http->FindSingleNode("(//tr[" . $this->eq($this->t("My Account")) . "]/following-sibling::tr[1])[1]");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Plan your stay,")) . "]", null, true, "#{$this->opt($this->t("Plan your stay,"))}\s*(?:Mrs?\.\s*)?(.{3,}?)\.#");
        }

        if (!empty($traveller)) {
            $traveller = preg_replace("/(様|님)\s*$/u", '', $traveller);
            $traveller = preg_replace("/^\s*(Signor|Dr\.|meneer|Sr\.|Sra\.)\s+/u", '', $traveller);
            $h->general()
                ->traveller($traveller, true);
        }

        // XXXXX2364
        $account = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Account'))}]/ancestor::table[{$this->contains($this->t('Account'))} and {$this->contains($this->t('Points'))} and {$this->contains($this->t('Status'))}][1]/descendant::table[1]/descendant::td[1]", null, true, "/^[Xx]*\d{4,}$/");
        $balance = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Account'))}]/ancestor::table[{$this->contains($this->t('Account'))} and {$this->contains($this->t('Points'))} and {$this->contains($this->t('Status'))}][1]/descendant::table[2]/descendant::td[1]", null, true, "/([\d\.\, ]+)/");

        if (empty($balance)) {
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Points'))}]/ancestor::table[1]", null, true, "/([\d\.\,]+)/");
        }

        $status = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Account'))}]/ancestor::table[{$this->contains($this->t('Account'))} and {$this->contains($this->t('Points'))} and {$this->contains($this->t('Status'))}][1]/descendant::table[3]/descendant::td[1]");

        if (!empty($account) && !empty($status)) {
            $st = $email->add()->statement();

            $st->setBalance(str_replace([',', '.', ' '], '', $balance));

            $st->setNumber(str_replace('X', '', $account))->masked('left');

            if (strlen($status) < 70) {
                $st->addProperty('Level', $status);
            }

            $name = $this->http->FindSingleNode("(//tr[" . $this->eq($this->t("My Account")) . "]/following-sibling::tr[1])[1]");

            if (!empty($name)) {
                $name = preg_replace("/(様|님)\s*$/u", '', $name);
                $st->addProperty('Name', $name);
            }
        } else {
            $accounts = $this->http->FindNodes("//tr[{$this->eq($this->t("Account"))}]/preceding-sibling::tr[1]", null, '/^[Xx]*\d{4,}$/');
            $accounts = array_filter($accounts);

            if (count($accounts)) {
                $h->program()->accounts(array_unique($accounts), true);
            }
        }

        $checkInArr = $this->http->FindNodes("//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Check-In:"))}]/following-sibling::*[$xpathCell and normalize-space()][position()<3]");
        $checkIn = $this->normalizeDate(implode(' ', array_unique($checkInArr)));

        $checkOutArr = $this->http->FindNodes("//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Check-Out:"))}]/following-sibling::*[$xpathCell and normalize-space()][position()<3]");
        $checkOut = $this->normalizeDate(implode(' ', array_unique($checkOutArr)));
        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut)
            ->rooms($this->http->FindSingleNode("(//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Number of rooms"))}]/following-sibling::*[$xpathCell and normalize-space()][1])[1]",
                null, true, "/^(\d{1,3})\s*{$this->opt($this->t("Room1"))}/ui"), false, true);

        $guestsText = $this->http->FindSingleNode("(//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Guests per room"))}]/following-sibling::*[$xpathCell and normalize-space()][1])[1]");

        if (preg_match("#\b(\d{1,3})\s*{$this->opt($this->t("Adult"))}#iu", $guestsText, $matches)) {
            $h->booked()->guests($matches[1]);
        } elseif (preg_match("#{$this->opt($this->t("Adult"))}\s*(\d{1,3})\s*{$this->opt($this->t("AdultPostfix"))}#iu", $guestsText, $matches)) {
            $h->booked()->guests($matches[1]);
        }

        if (preg_match("#\b(\d{1,3})\s*" . $this->opt($this->t("Kid")) . "#i", $guestsText, $matches)) {
            $h->booked()->kids($matches[1]);
        } elseif (preg_match("#{$this->opt($this->t("Kid"))}\s*(\d{1,3})\s*{$this->opt($this->t("AdultPostfix"))}#iu", $guestsText, $matches)) {
            $h->booked()->kids($matches[1]);
        }

        $roomType = $roomDescription = $roomRate = null;

        $roomTypeTexts = [];
        $roomTypeNodes = $this->http->XPath->query("//*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Room Type"))}]");

        foreach ($roomTypeNodes as $roomTypeNode) {
            $roomHeader = $this->http->FindSingleNode("preceding::*[$xpathCell and not(.//*[$xpathCell]) and normalize-space()][1]", $roomTypeNode,
                true, "/^({$this->opt($this->t("Room2"))}\s*\d{1,3})\b/i");
            $roomInfoTexts = $this->http->FindNodes("following::*[$xpathCell and not(.//*[$xpathCell]) and string-length(normalize-space())>3][1]/descendant::text()[normalize-space() and not(ancestor::a)]", $roomTypeNode);
            $roomInfo = implode(' ', $roomInfoTexts);

            if ($roomHeader && preg_match('/^([^,]+)(?:,\s*(.+))?/', $roomInfo, $matches)) {
                if (empty($matches[2])) {
                    $roomTypeTexts[] = [$roomHeader, $matches[1]];
                } else {
                    $roomTypeTexts[] = [$roomHeader, $matches[1], $matches[2]];
                }
            }
        }

        if (count($roomTypeTexts) === 1) {
            $roomType = $roomTypeTexts[0][1];

            if (!empty($roomTypeTexts[0][2])) {
                $roomDescription = $roomTypeTexts[0][2];
            }
        } elseif (count($roomTypeTexts)) {
            foreach ($roomTypeTexts as $roomTypeText) {
                $roomType .= '; ' . $roomTypeText[0] . ': ' . $roomTypeText[1];
            }
            $roomType = trim($roomType, ',; ');
        }

        $xpathFragment5 = "//tr[not(.//tr) and " . $this->eq($this->t("Summary Of Charges")) . "]";

        $rates = $this->http->FindNodes($xpathFragment5 . "/following::*[$xpathCell and not(.//*[$xpathCell]) and {$this->contains($this->t(" per night per room"))}]");
        $rateTypes = implode(' ', array_unique(array_filter($this->http->FindNodes("//text()[contains(normalize-space(), ' per night per room')]/following::text()[normalize-space()][1]"))));
        $rateSums = [];
        $cur = $per = '';
        $rateValues = [];

        foreach ($rates as $rate) {
            if (preg_match("/^\s*(?<night>\d+) .+\W{$this->opt($this->t("at"))}\s*(?<amount>\d[,.\d\s]*?)\s*(?<currency>[A-Z]{3})\s*(?<per>{$this->opt($this->t(" per night per room"))})/u", $rate, $matches)
                || preg_match("/^\s*(?<night>\d+) .+\W{$this->opt($this->t("at"))}\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\d\s]*?)\s*(?<per>{$this->opt($this->t(" per night per room"))})/u", $rate, $matches)
            ) {
                // 1 Night at 118.300 JOD per night per room
                $decimalsRate = $this->detectNonStandardDecimals($matches['amount'], $matches['currency']);
                $rateSums[] = $this->normalizeAmount($matches['amount'], empty($decimalsRate) ? null : $decimalsRate);
                $cur = $matches['currency'];
                $per = $matches['per'];

                for ($i = 0; $i < $matches['night']; $i++) {
                    if ($rate !== null) {
                        $rateValues[] = $matches['amount'] . ' ' . $matches['currency'];
                    }
                }
            }
        }
        sort($rateSums, SORT_NUMERIC);
        $rateSums = array_values(array_unique($rateSums));

        if (count($rateSums) == 1) {
            $roomRate = $rateSums[0] . ' ' . $cur . ' ' . trim($per);
        } elseif (count($rateSums) > 1) {
            $roomRate = $rateSums[0] . '-' . end($rateSums) . ' ' . $cur . ' ' . trim($per);
        }

        $nights = 0;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = (int) date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
        }

        if ($nights === 0 || count($rateValues) !== $nights) {
            $rateValues = null;
        }

        if (!empty($roomType) || !empty($roomDescription) || $roomRate !== null || $rateValues !== null) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            if (!empty($rateValues)) {
                $room->setRates($rateValues);
            } elseif ($roomRate !== null) {
                $room->setRate($roomRate);
            }

            if (!empty($rateTypes)) {
                $rateTypes = str_replace('>', '', $rateTypes);
                $room->setRateType($rateTypes);
            }
        }

        $totalPayment = $this->http->FindSingleNode('(' . $xpathFragment5 . "/following::*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Total for Stay (all rooms)"))}]/following-sibling::*[$xpathCell and normalize-space()][1])[1]");

        if ($totalPayment === null) {
            $totalPayment = $this->http->FindSingleNode($xpathFragment5 . "/following::text()[{$this->eq($this->t("Total for Stay (all rooms)"))}]/following::node()[normalize-space()][1]");
        }

        if (preg_match('/^\s*(?<amount>\d[,.\'\d\s]*?)\s*(?<currency>[A-Z]{3})\s*$/', $totalPayment, $matches)
            || preg_match('/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d\s]*?)\s*$/', $totalPayment, $matches)
        ) {
            // 1,185.29 USD
            $decimalsTotal = $this->detectNonStandardDecimals($matches['amount'], $matches['currency']);
            $h->price()
                ->total($this->normalizeAmount($matches['amount'], empty($decimalsTotal) ? null : $decimalsTotal))
                ->currency($matches['currency']);

            $taxes = $this->http->FindSingleNode('(' . $xpathFragment5 . "/following::*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("Estimated Government Taxes & Fees"))}]/following-sibling::*[$xpathCell and normalize-space()][1])[1]");

            if ($taxes === null) {
                $taxes = $this->http->FindSingleNode($xpathFragment5 . "/following::text()[{$this->eq($this->t("Estimated Government Taxes & Fees"))}]/following::node()[normalize-space()][1]");
            }

            if (preg_match('/^(?<amount>\d[,.\'\d\s]*?)\s*' . preg_quote($matches['currency'], '/') . '/', $taxes, $m)) {
                $decimalsTax = $this->detectNonStandardDecimals($m['amount'], $matches['currency']);
                $h->price()->tax($this->normalizeAmount($m['amount'], empty($decimalsTax) ? null : $decimalsTax));
            }

            $fees = $this->http->XPath->query($xpathFragment5 . "/following::*[$xpathCell and not(.//*[$xpathCell]) and {$this->starts($this->t("fees"))}]/following-sibling::*[$xpathCell and normalize-space()][1]");

            foreach ($fees as $fee) {
                $feeText = $this->http->FindSingleNode("ancestor::tr[1]", $fee);

                if (preg_match("/({$this->opt($this->t("fees"))})\s*(?<amount>\d[,.\'\d\s]*?)\s*" . preg_quote($matches['currency'], '/') . "/", $feeText, $m)) {
                    $decimalsFee = $this->detectNonStandardDecimals($m['amount'], $matches['currency']);
                    $h->price()->fee($m[1], $this->normalizeAmount($m['amount'], empty($decimalsFee) ? null : $decimalsFee));
                }
            }
        }

        /*
            Info:
            FNA - Free Night Award
        */
        $freeNightsPhrases = ['FNA UP TO', 'FNA up to', 'FNA Up To', 'FREE NIGHT', 'free night', 'Free Night'];

        $points = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t("Total Points Redeemed"), "translate(.,':','')")}]/following-sibling::*[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if ($points !== null) {
            $points = preg_replace('/\D/', '', $points); // 56,000 Points  ->  56000
        }

        // these are also points, refs #25183
        $pointsHidden = array_filter($this->http->FindNodes("///text()[{$this->eq($this->t("Total Points Redeemed"), "translate(.,':','')")}]/ancestor::tr[{$this->starts($this->t("Total Points Redeemed"))}]/preceding-sibling::tr/"
            . "descendant-or-self::tr[ count(*)=2 and *[2][{$this->contains($this->t("Points"))}] ]/*[1][{$this->contains($this->t("up to"))}]",
        null, "/(?:\(|\s){$this->opt($this->t("up to"))}\s+(\d+\s*[Kk]\b|\d[,.\d\s]*)\s*[[:alpha:]]+\s*\)?\s*$/iu"));
        $pointsHidden = preg_replace('/^(\d+)\s*[Kk]$/', '$1,000', $pointsHidden); // 85K  ->  85,000

        if ($points !== null || count($pointsHidden) > 0) {
            $pointsAmounts = [];

            if ($points !== null) {
                $pointsAmounts[] = PriceHelper::parse($points);
            }

            foreach ($pointsHidden as $phVal) {
                $pointsAmounts[] = PriceHelper::parse(preg_replace('/\D/', '', $phVal));
            }

            if (count($pointsAmounts) > 0) {
                $h->price()->spentAwards(array_sum($pointsAmounts) . ' Points');
            }

            $freeNights = $this->http->XPath->query("//text()[{$this->eq($this->t("Total Points Redeemed"), "translate(.,':','')")}]/ancestor::tr[{$this->starts($this->t("Total Points Redeemed"))}]/preceding-sibling::tr/"
                . "descendant-or-self::tr[ count(*)=2 and *[1][{$this->contains($freeNightsPhrases)}] and *[2][{$this->eq(preg_replace('/(.+)/', '0 $1', $this->t("Points")))}] ]")->length;

            if ($freeNights > 0) {
                $h->booked()->freeNights($freeNights);
            }
        }

        // CancellationPolicy
        $cancellationPolicyTexts = $this->http->FindNodes("//tr[not(.//tr) and " . $this->contains($this->t("Cancellation Policy")) . "]/following::table[normalize-space(.)][1]/descendant::td[not(.//td) and string-length(normalize-space(.))>3]");
        $cancellationPolicyText = implode(' ', $cancellationPolicyTexts);

        if ($cancellationPolicyText) {
            $h->general()->cancellation($cancellationPolicyText);
            $this->detectDeadLine($h, $cancellationPolicyText);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/You may cancel your reservation for no charge until (.+? \d{4}) \(\d+ day\[s\] before arrival\)/i", $cancellationText, $m) // en
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        } elseif // should be previous
            (preg_match("/Você poderá cancelar sua reserva sem custos até as (?<h>\d+)[:h](?<m>\d+) \(horário do hotel\), (?<date>.+? \d{4})/i", $cancellationText, $m) // pt
                || preg_match("/You may cancel your reservation for no charge before (?<h>\d+)[:h](?<m>\d+(?:[ ]*[ap]m)?) local hotel time on (?<date>.+?) \(\d+ day/i", $cancellationText, $m) // pt
                || preg_match("/Вы можете бесплатно отменить бронирование до (?<h>\d+):(?<m>\d+(?:[ ]*[ap]m)?) по местному времени (?<date>.+?) \(/u", $cancellationText, $m) // ru
        ) {
            $h->booked()->deadline(strtotime($m['h'] . ':' . $m['m'], $this->normalizeDate($m['date'])));
        } elseif (preg_match("/Podrá anular su reserva sin cargo hasta el\s*(.+ \d{4}.*?)[.(]/i", $cancellationText, $m) // es
            || preg_match("/Você poderá cancelar sua reserva sem custos até (.+ \d{4}.{0,10}?)\(/i", $cancellationText, $m) // pt
            || preg_match("/Vous pouvez annuler votre réservation sans pénalité jusqu'au (.+ \d{4}.*?)\(/i", $cancellationText, $m) // fr
            || preg_match("/Sie können Ihre Reservierung bis (.{5,25} \d{4}.{0,20}?)\(/i", $cancellationText, $m) // de
            || preg_match("/Sie können Ihre Reservierung bis zum (.{5,20} \d{4}.{0,20}?)\s*kostenfrei stornieren\./i", $cancellationText, $m) // de
            || preg_match("/È possibile annullare la prenotazione senza costi fino a domenica (.+ \d{4}.*?)\(/i", $cancellationText, $m) // it
            || preg_match("/在飯店當地時間(.+?)之前可/i", $cancellationText, $m) // zh
        ) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        } elseif (preg_match("/お客様のご予約は、(?<year>\d{4})年(?<month>\d+)月(?<day>\d+)日 \(.+?\)（到着\d+日前）/iu", $cancellationText, $m) // ja
            || preg_match("/^도착 \d+일 전인 (?<year>\d{4})년 (?<month>\d+)월 (?<day>\d+)일 목요일까지는 위약금 없이/iu", $cancellationText, $m) // ko
            || preg_match('/[ ]+(?<year>\d{4})\w+[ ]+(?<month>\d{1,2})\w+[ ]+(?<day>\d{1,2})\w+[ ]+.+위약금 없이 예약을 취소하실 수 있습니다/u', $cancellationText, $m) // ko
        ) {
            $h->booked()->deadline(strtotime($m['year'] . '-' . $m['month'] . '-' . $m['day']));
        } elseif (preg_match("/^호텔 현지 시간 기준 (?<year>\d{4})년 (?<month>\d+)월 (?<day>\d+)일 수요일, 오후 (?<time>\d+:\d+)까지 무료로 예약을 취소하실 수 있습니다./iu", $cancellationText, $m) // ko
        ) {
            $h->booked()->deadline(strtotime($m['year'] . '-' . $m['month'] . '-' . $m['day'] . ', ' . $m['time'] . 'pm'));
        } elseif (preg_match('/Podrá cancelar su reserva sin cargo hasta las (?<time>\d{1,2}:\d{2}(?:\s*h)?), hora local del hotel, del (?<date>.+? \d{4}) \(/u', $cancellationText, $m) // es
            || preg_match('/^Você poderá cancelar sua reserva, custos até as (?<time>\d+[h:]\d+) (?:\(horário do hotel\)|hs no horário do hotel), (?<date>[\w\-]+, .+? \d{4}) \(/u', $cancellationText, $m) // pt
            || preg_match("/Please note that you may cancel your reservation for no charge before (?<time>{$this->patterns['time']}) local hotel time on (?<date>.{3,25}? \d{4})\s*\./i", $cancellationText, $m) // en
            || preg_match("/You may cancel your reservation for no charge until (?<time>{$this->patterns['time']}) hotel time on (?<date>.{3,25}? \d{4})\s*\./i", $cancellationText, $m) // en
            || preg_match("/您可于酒店当地时间\s*(?<time>(?:夜间\s*)?(?:[[:alpha:]]{2}\s*)?{$this->patterns['time']})\s*之前免费取消您的预订，截止日期为\s*(?<date>\d{4}.{4,15}?)(?:\s+[- [:alpha:]]+)?[。.]/iu", $cancellationText, $m) // zh
            || preg_match("/このご予約は、次の日にちのホテル現地時刻(?<time>(?:\s*午後)?\s*\d{1,2}[:時]+\d{1,2})分までは無料でキャンセルできます\s+(?<date>[^。.]{6,}?)\s*（到着\d+日前）[。.]/iu", $cancellationText, $m) // ja
            || preg_match("/Sie können diese Reservierung bis (?<time>{$this->patterns['time']} Uhr) Hotelzeit am (?<date>.{3,25}? \d{4})\s* \(\d+ Tag\(e\) vor Ankunft\) kostenfrei stornieren\./i", $cancellationText, $m) // en
        ) {
            // 午後11時59
            $m['time'] = preg_replace("/^(午後)\s*(\d{1,2})[:時]+(\d{1,2})$/u",
                preg_match('/(\d+)\s*(?:월|月)/u', $m['date']) ? '$1 $2:$3' : '$2:$3 PM', // ja VS en
            $m['time']);
            $m['time'] = preg_replace("/^(\d{1,2})[.](\d{1,2})\b/u", '$1:$2', $m['time']);
            $h->booked()->deadline($this->normalizeDate($m['date'] . ', ' . $m['time']));
        }
    }

    private function parseEmailCancel(Email $email)
    {
        $this->logger->info(__METHOD__);
        $h = $email->add()->hotel();

        $xpathImgAddress = "{$this->contains('map_icon', '@src')} or {$this->eq('Address', '@alt')} or ({$this->contains('removed', '@alt')} and {$this->contains('Address', '@alt')})";
        $xpathImgPhone = "{$this->contains('phone_icon', '@src')} or {$this->eq('Telefone', '@alt')} or ({$this->contains('removed', '@alt')} and {$this->contains('Telefone', '@alt')})";

        $hotelName = $this->http->FindSingleNode("//img[{$xpathImgAddress}]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//table[count(descendant::tr)=3]/descendant::a[contains(@href, 'res-marriott.com/T/v') and contains(@title, 'Opens in a new window') and ancestor::tr[2][contains(., '+') and contains(., '-')]][1]/preceding::a[1]");
        }
        $address = $this->http->FindSingleNode("(//img[{$xpathImgAddress}]/ancestor::td[1]/following-sibling::td)[1]");

        if (!$address) {
            $address = $this->http->FindSingleNode("//table[count(descendant::tr)=3]/descendant::a[contains(@href, 'res-marriott.com/T/v') and contains(@title, 'Opens in a new window') and ancestor::tr[2][contains(., '+') and contains(., '-')]][1]");
        }

        if (empty(trim($address))) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='{$hotelName}']/following::text()[normalize-space()][1]");
        }
        $phone = $this->http->FindSingleNode("(//img[{$xpathImgPhone}]/ancestor::td[1]/following-sibling::td)[last()]", null, true, "/^{$this->patterns['phone']}$/");

        if (!$phone) {
            $phone = $this->http->FindSingleNode("//table[count(descendant::tr)=3]/descendant::a[contains(@href,'res-marriott.com/T/v') and contains(@title,'Opens in a new window') and ancestor::tr[2][contains(.,'+') and contains(.,'-')]][1]/following::text()[contains(.,'+') and contains(.,'-')][1]", null, true, "/^{$this->patterns['phone']}$/");
        }

        if (preg_match("/http/u", $hotelName)) {
            $hotelName = $this->re("/^(.+)\s*http/", $hotelName);
        }

        if (preg_match("/http/u", $address)) {
            $address = $this->re("/^(.+)\s*http/", $address);
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Confirmation Number"))}])[1]",
            null, true, "#^{$this->opt($this->t('Confirmation Number'))}\s*([A-Z\d]{5,})#u");
        $descr = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Confirmation Number"))}])[1]",
            null, true, "#^({$this->opt($this->t('Confirmation Number'))})\s*[A-Z\d]{5,}#");

        if (!empty($confNo) && !empty($descr)) {
            $h->general()
                ->confirmation($confNo, $descr);
        } else {
            $h->general()
                ->noConfirmation();
        }

        // Park Sunjoo님    |    Hua, Zhang
        $pax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]/preceding::td[normalize-space()][1]", null, true, '/^[[:alpha:]][-,.\'[:alpha:] ]*[[:alpha:]]$/u');

        if (!empty($pax)) {
            $pax = preg_replace("/(様|님)\s*$/u", '', $pax);
            $h->general()
                ->traveller($pax);
        }

        $h->general()
            ->status($this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][last()]"))
            ->cancelled();

        $cancelNum = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Cancellation Number:"))}]",
            null, true, "#^[^:]+:\s*([A-Z\d]{5,})#");

        if (!empty($cancelNum) && $cancelNum !== $confNo) {
            $h->general()->cancellationNumber($cancelNum);
        }

        $dates = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Cancellation Number:"))}]/ancestor::tr[1]/following::tr[normalize-space()!=''][1]");

        if (preg_match("#(.*\d{2,4}).*\s*–\s*(.+)#", $dates, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        $rates = $this->http->FindNodes("//text()[{$this->contains($this->t("Rate Information"))}]/ancestor::table[1]/following-sibling::table[normalize-space()!='']//text()[{$this->contains($this->t(' per night per room'))}]");

        foreach ($rates as $rate) {
            if (preg_match("/^\s*(?<amount>\d[,.\'\d ]*?)\s*(?<currency>[A-Z]{3})\s*(?<per>{$this->opt($this->t(" per night per room"))})/", $rate, $m)) {
                $decimalsRate = $this->detectNonStandardDecimals($m['amount'], $m['currency']);
                $ratesValue[] = $this->normalizeAmount($m['amount'], empty($decimalsRate) ? null : $decimalsRate);
                $ratesCurrency = $m['currency'];
                $ratesPer = $m['per'];
            }
        }

        if (!empty($ratesValue) && !empty($ratesCurrency) && !empty($ratesPer)) {
            $room = $h->addRoom();

            if (count($ratesValue) == 1) {
                $room->setRate($ratesValue[0] . ' ' . $ratesCurrency . $ratesPer);
            } else {
                sort($ratesValue);
                $room->setRate($ratesValue[0] . ' - ' . end($ratesValue) . ' ' . $ratesCurrency . $ratesPer);
            }
        }

        return true;
    }

    /**
     * Detect and return symbols floating-point for non-standard decimals.
     *
     * @param string|null $amount String with amount
     * @param string|null $currency String with currency
     */
    private function detectNonStandardDecimals(?string $amount, ?string $currency): ?string
    {
        $specialCurrencies = ['OMR', 'JOD', 'BHD', 'KWD'];

        if (!in_array(trim($currency), $specialCurrencies)) {
            return null;
        }
        $amount = trim($amount);

        if (preg_match('/^(?:\d+[,\'\s])*\d+\.\d{3}$/', $amount)) {
            // 1,258.943
            $decimals = '.';
        } elseif (preg_match('/^(?:\d+[.\'\s])*\d+,\d{3}$/', $amount)) {
            // 1.258,943
            $decimals = ',';
        }

        return $decimals ?? null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
//        $this->logger->debug('DATE (in): ' . $instr);

        if (!preg_match('/\b\d{2,4}\b/', $instr)) {
            return false;
        }
        $instr = preg_replace('/夜间\s*下午/u', '下午', $instr);
        $instr = preg_replace([
            '/([一]|\b)(?:午前|上午|오전)\s*(\d{1,2}:\d{2})/u', // , 上午 11:00    ->    11:00 AM
            '/([一]|\b)(?:午後|下午|오후|夜间)\s*(\d{1,2}:\d{2})/u', // 一下午 06:00    ->    06:00 PM
        ], [
            '$1$2 AM',
            '$1$2 PM',
        ], $instr);
        $instr = preg_replace("/^(.+){$this->opt($this->t('Guaranteed early check-in'))}$/", '${1}00:00', $instr);
        $in = [
            // Thursday, February 27, 2020 (Thursday)
            '/^\s*[-[:alpha:]]+[ ]*,[ ]*([[:alpha:]]+)\s+(\d+)[ ]*,[ ]*(\d{4})(?:\s*\([-[:alpha:]]*\))?\s*$/ui',
            // Tuesday, September 18, 2018, 04:00 PM
            '/^\s*[-[:alpha:]]+[ ]*,[ ]*([[:alpha:]]+)\s+(\d+)[ ]*,[ ]*(\d{4})(?:\s*\([-[:alpha:]]*\))?[, ]+(\d+:\d+(?:[ ]*[AP]M)?)$/iu',

            // viernes 1 de junio de 2018, 12:00 h; Segunda-feira, 2 de Dezembro de 2019 15:00 hs
            // Montag, 6. August 2018, 13:00 Uhr
            '/^\s*[-[:alpha:]]{2,}\s*[, ]\s*(\d{1,2})\.?\s+(?:de\s+)?([-[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})\s*[, ]\s*(?:[Hh]\s+)?(\d{1,2}) ?[h:] ?(\d{2})[ ]*(?:[hrs]*|Uhr)\s*$/iu',
            //Montag, 6. August 2018; jeudi 11 avril 2019; Sexta-feira, 3 de Maio de 2019; mié, 09 ene, 2019
            "/^\s*[-[:alpha:]]{2,}\.?\s*[, ]\s*(\d{1,2})\.?\s+(?:de\s+)?([-[:alpha:]]{3,})\.?,?\s+(?:de\s+)?(\d{4})\s*\s*$/ui",

            // 2020年1月31日 星期五, 11:00 AM
            "/^(\d{4})\s*(?:년|年)\s*(\d{1,2})\s*(?:월|月)\s*(\d{1,2})\s*(?:일|日)(?:[\s(]+[-[:alpha:]]+)?[-一),\s]+\s*({$this->patterns['time']})\s*$/iu",
            // 2019/07/19    |    2019/03/18 (月)
            '/^(\d{4}\/\d{1,2}\/\d{1,2})\D*[)(\w]*$/u',
            '/^(\d{4})\.(\d{1,2})\.(\d{1,2})\D*$/u', // 2019.11.18, 星期一

            // 4 июля 2021 г. 14:00
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})(?:\s*г\.)?[, ]+(\d+:\d+(?:[ ]*[AP]M)?)$/iu',
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})(?:\s*г\.)?$/iu',
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3, $4",

            "$1 $2 $3, $4:$5",
            "$1 $2 $3",

            '$1-$2-$3, $4',
            '$1',
            '$1-$2-$3',

            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = str_replace('. ', ' ', preg_replace($in, $out, $instr));
//        $this->logger->debug('DATE (out): ' . $str);
        if ('en' !== $this->lang && preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
