<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-136598828.eml, goldpassport/it-149925230.eml, goldpassport/it-149929168.eml, goldpassport/it-152880333.eml, goldpassport/it-155989335.eml, goldpassport/it-162053134.eml, goldpassport/it-28614433.eml, goldpassport/it-28646751.eml, goldpassport/it-296593846.eml, goldpassport/it-33582619.eml, goldpassport/it-33689880.eml, goldpassport/it-36636142.eml, goldpassport/it-40987954.eml, goldpassport/it-45988640.eml, goldpassport/it-48328871.eml, goldpassport/it-50499498.eml, goldpassport/it-50837655.eml, goldpassport/it-59545551.eml, goldpassport/it-636307623.eml, goldpassport/it-666863426.eml, goldpassport/it-677440419.eml, goldpassport/it-678026128.eml, goldpassport/it-8135683.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = "hyatt.com";
    public $reBody = [
        'en' => [
            ['Confirmation Number', 'Cancellation Number', 'Confirmation number', 'Confirmation # /'],
            ['Reservation Reminder', 'Reservation Change', 'Reservation Confirmation', 'Reservation Cancellation', 'Hyatt Regency Atlanta Team',
                'Online Check-In', 'Thank you for choosing Hyatt', 'Thank you for staying at', 'Reservation Update',
                'CANCELLATION POLICY', 'Room Ready', 'Hotel Bill', 'Express Check-In Error', ],
        ],
        'de' => [
            ['Reservierungsnummer', 'Datum:'],
            ['Reservierungsänderung', 'Ihre Reservierung ist bestätigt', 'Ihren Aufenthalt verwalten', 'Express Check-out',
                'Online-Check-in', ],
        ],
        'es' => [
            ['Número de confirmación', 'Número de cancelación'],
            ['Cancelación de la reserva', 'Confirmación de reserva', 'Registro de entrada', 'Cambio de reserva', 'Cancelación de reserva',
                'Modificación de reserva', 'Recordatorio de reserva', 'Check-in electrónico', 'Check-out exprés', ],
        ],
        'zh' => [
            ['确认号码', '取消号码', '確認編號'],
            ['预订确认', '酒店帐单', '在线登记入住', '预订更改', '预订取消', '快速退房', '管理住宿', '快速结帐', '網上⼊住登記'],
        ],
        'ko' => [
            ['확인 번호', '확인 번호 :'],
            ['예약 확인', '예약 변경', '예약이 취소되었습니다.', '온라인 체크인'],
        ],
        'pt' => [
            ['Número da confirmação', 'Cancelamento da reserva'],
            ['Gerenciar minha reserva', 'Checkout expresso'],
        ],
        'fr' => [
            ['Numéro de confirmation:', 'Numéro de confirmation :'],
            ['Gérer votre séjour'],
        ],
        'ja' => [
            ['予約確認番号 :', '予約確認番号:', 'キャンセル番号'],
            ['オンラインチェックイン', 'ご予約の確認', 'ご予約の管理', '予約のキャンセル', 'ご利用明細書'],
        ],
    ];
    public $reSubject = [
        'en'  => '#Hyatt.+?(?:Reminder|Confirmation|Change|Cancellation).+?[A-Z\d]+\s*$#',
        'en4' => '/Hyatt Online Check-In Invitation/',
        'de'  => '/Hyatt.+?(?:Reservierungsanderung|Reservierungsbestätigung|Stornierung der Reservierung).+?[A-Z\d]{5,}\s*$/u',
        'es'  => '#Hyatt.+?(?:Confirmación|Cancelación|Modificación).+?[A-Z\d]+\s*$#u',
        'zh'  => '#凯悦.+?预订确认.+?[A-Z\d]+\s*$#',
        'zh2' => '#凯悦.+预订更改#',
        'zh3' => '#您的酒店账单已准备好#',
        'zh4' => '#凯悦在线登记入住邀请#',
        'zh5' => '#凯悦.+?预订取消.+?[A-Z\d]+\s*$#',
        'zh6' => '#訂房確認\s\-\s\d+$#',
        'zh7' => '#.*凯悦.* - [[:alpha:] ]+ - [A-Z ]+ - \d{4}\w\s*$#',
        'pt'  => '#Hyatt.+?(?:Confirmação|cancelamento) de reserva.+?$#u',
        'fr'  => '/Hyatt.+? - Confirmation de réservation -/',
        'ja'  => '/ハイアット オンラインチェックインのご案内/',
        'ja2' => '/ハイアット オンラインチェックインのご案内/',
        'ja3' => '/予約のキャンセル/',
        'ko'  => '/안다즈 싱가포르/',
    ];
    public $lang = '';
    public $dateLang = '';
    public static $dict = [
        'en' => [ // it-28614433.eml, it-33582619.eml, it-33689880.eml, it-50499498.eml, it-8135683.eml
            'Your reservation is' => ['Your reservation is', 'Your reservation has'],
            'Confirmation Number' => ['Confirmation Number', 'Cancellation Number', 'Confirmation number', 'Confirmation # /'],
            'checkIn'             => ['Check-In', 'Check-in', 'Checkin'],
            'checkOut'            => ['Check-Out', 'Check-out', 'Checkout'],
            'Type of Rate'        => ['Type of Rate', 'Rate Name:'],
            'cancelled Text'      => ['Your reservation is cancelled', 'Reservation Cancellation'],
        ],
        'de' => [
            'Your reservation is' => 'Ihre Buchung ist',
            'Confirmation Number' => 'Reservierungsnummer',
            'Guest Name'          => 'Name des Gastes',
            'checkIn'             => 'Anreise',
            'checkOut'            => 'Abreise',
            'Date'                => 'Datum',
            'Time'                => 'Uhrzeit',
            'Number of Adults'    => 'Anzahl der Erwachsenen',
            'Number of Children'  => 'Anzahl der Kinder',
            'Room(s) Booked'      => 'Gebuchte(s) Zimmer',
            'Room Type'           => 'Zimmertyp',
            //			'Room Description' => '',
            'CANCELLATION POLICY'    => ['Stornierungsrichtlinie', 'STORNIERUNGSRICHTLINIE'],
            'Nightly Rate per Room:' => ['Zimmerpreis pro Nacht:', 'Übernachtungspreis pro Zimmer:'],
            'Type of Rate'           => 'Preiskategorie:',
            //            'Multiple Dates' => '',
            'Membership'     => 'Mitgliedsnummer',
            'cancelled Text' => ['Ihre Buchung wurde storniert', 'Stornierung der Reservierung'],
        ],
        'es' => [
            'Your reservation is' => 'Su reserva ha sido',
            'Confirmation Number' => ['Número de confirmación', 'Número de cancelación'],
            'Guest Name'          => ['Nombre del huésped', 'de la persona alojada:'],
            'checkIn'             => ['Día de entrada', 'Registro de entrada'],
            'checkOut'            => ['Día de salida', 'Registro de salida'],
            'Date'                => 'Fecha',
            'Time'                => 'Hora',
            'Number of Adults'    => ['Adultos', 'Personas adultas'],
            'Number of Children'  => ['Niños', 'Menores de edad'],
            'Room(s) Booked'      => 'Habitación(es) reservada(s)',
            'Room Type'           => 'Tipo de habitación',
            //			'Room Description' => '',
            'CANCELLATION POLICY'    => ['Política de cancelación', 'POLÍTICA DE CANCELACIÓN:'],
            'Nightly Rate per Room:' => 'Tarifa por habitación por noche:',
            'Type of Rate'           => 'Tipo de tarifa',
            'Cancellation Number'    => 'Número de cancelación',
            //            'Multiple Dates' => '',
            'Membership'     => 'Membresía',
            'cancelled Text' => ['Cancelación de reserva', 'Su reserva ha sido cancelada'],
        ],
        'zh' => [ // it-28646751.eml, it-45988640.eml, it-50837655.eml, it-59545551.eml
            'Your reservation is'    => ['您的预订已', '您的訂房'],
            'Confirmation Number'    => ['确认号码', '確認編號', '取消号码'],
            'Guest Name'             => ['客人姓名', '賓客姓名'],
            'checkIn'                => '入住',
            'checkOut'               => '退房',
            'Date'                   => '日期',
            'Time'                   => ['时间', '時間'],
            'Number of Adults'       => ['成人人数', '成人人數'],
            'Number of Children'     => ['儿童人数', '兒童人數'],
            'Room(s) Booked'         => ['已预订客房', '已預訂客房', '已預訂客房數'],
            'Room Type'              => ['房间类型', '客房類型', '客房类型'],
            'Room Description'       => '房间描述',
            'CANCELLATION POLICY'    => ['预订取消政策', '預訂取消政策'],
            'Nightly Rate per Room:' => ['每房每晚房价:', '每間客房每晚價格:', '每房每晚房价'],
            'Type of Rate'           => ['房价类别', '房價類別'],
            'Cancellation Number'    => '取消号码',
            'Membership'             => ['會籍', '会员号'],
            //            'Multiple Dates' => '',
            //            'cancelled Text' => [''],
        ],
        'ko' => [ // it-36636142.eml
            'Your reservation is' => '예약이',
            'Confirmation Number' => '확인 번호',
            'Guest Name'          => '고객 이름',
            'checkIn'             => '체크인',
            'checkOut'            => '체크아웃',
            'Date'                => '날짜',
            'Time'                => '시간',
            'Number of Adults'    => '성인 수',
            'Number of Children'  => '어린이 수',
            'Room(s) Booked'      => '예약된 객실',
            'Room Type'           => '객실 유형',
            //            'Room Description' => '',
            'CANCELLATION POLICY'    => '취소 규정',
            'Nightly Rate per Room:' => '객실당 1박 요금:',
            'Type of Rate'           => '요금 유형',
            //            'Multiple Dates' => '',
            'Membership'     => '멤버십',
            'cancelled Text' => ['예약이 취소되었습니다.'],
        ],
        'pt' => [ // it-40987954.eml
            'Your reservation is' => 'A sua reserva está',
            'Confirmation Number' => ['Número da confirmação :', 'Número da confirmação:', 'Número da confirmação'],
            'Guest Name'          => 'Nome do hóspede:',
            'checkIn'             => ['Check-in', 'check-in'],
            'checkOut'            => ['Check-out', 'check-out'],
            'Date'                => 'Data',
            'Time'                => 'Horário',
            'Number of Adults'    => 'Número de adultos:',
            'Number of Children'  => 'Número de crianças:',
            'Room(s) Booked'      => 'Quarto(s) reservado(s):',
            'Room Type'           => ['Tipo de apartamento:', 'Tipo de quarto:'],
            //			'Room Description' => '',
            'CANCELLATION POLICY'    => ['Política de cancelamento:', 'POLÍTICA DE CANCELAMENTO:'],
            'Nightly Rate per Room:' => 'Diária por quarto:',
            'Type of Rate'           => 'Tipo de tarifa:',
            'Cancellation Number'    => 'Número de cancelamento:',
            //            'Multiple Dates' => '',
            'Membership' => 'Associação',
            //            'cancelled Text' => [''],
        ],
        'fr' => [ // it-48328871.eml
            'Your reservation is' => 'Votre réservation est',
            'Confirmation Number' => ['Numéro de confirmation:', 'Numéro de confirmation :'],
            'Guest Name'          => ['Nom du client:', 'Nom du client :'],
            'checkIn'             => ['Arrivée'],
            'checkOut'            => ['Départ'],
            'Date'                => 'Date',
            'Time'                => 'Heure',
            'Number of Adults'    => ["Nombre d'adultes:", 'Nombre d\'adultes :'],
            'Number of Children'  => ["Nombre d'enfants", "Nombre d'enfants :"],
            'Room(s) Booked'      => ['Chambre(s) réservée(s):', 'Chambre(s) réservée(s) :'],
            'Room Type'           => 'Type de chambre',
            // 'Room Description' => '',
            'CANCELLATION POLICY'    => ["Politique d'annulation:", "POLITIQUE D'ANNULATION :"],
            'Nightly Rate per Room:' => ['Tarif par chambre et par nuit:', 'Tarif par chambre et par nuit :'],
            'Type of Rate'           => ['Type de tarif:', 'Type de tarif :'],
            // 'Cancellation Number' => '',
            // 'Multiple Dates' => '',
            //            'Membership' => '',
            //            'cancelled Text' => [''],
        ],
        'ja' => [
            //'Your reservation is' => '',
            'Confirmation Number' => ['予約確認番号 :', '予約確認番号:'],
            'Cancellation Number' => 'キャンセル番号:',
            'Guest Name'          => 'お名前:',
            'checkIn'             => ['チェックイン'],
            'checkOut'            => ['チェックアウト'],
            'Date'                => '日付:',
            'Time'                => '時間:',
            'Number of Adults'    => "大人の人数:",
            'Number of Children'  => "お子様の人数:",
            'Room(s) Booked'      => ['客室の種類:', '客室数:'],
            'Room Type'           => '客室タイプ:',
            //'Room Description' => '',
            'CANCELLATION POLICY'    => "キャンセル規約:",
            'Nightly Rate per Room:' => ['1部屋１泊あたりの客室料金:', '1泊あたりの客室料金:'],
            'Type of Rate'           => '料金タイプ:',
            // 'Cancellation Number' => '',
            'Multiple Dates' => '複数の日付',
            'Membership'     => '会員番号',
            'cancelled Text' => ['ご予約がキャンセルされました', '予約のキャンセル'],
        ],
    ];
    private $date;
    private $subjectValue = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->date = strtotime($parser->getHeader('date'));

        $this->subjectValue = $parser->getSubject();

        if (preg_match("#.+\s*-\s*(.+?)\s*-\s-\d{5,}\s*$#", $this->subjectValue, $m)) {
            $date = $this->normalizeDate($m[1]);

            if (!empty($date)) {
                $this->date = $date;
            }
        }

        if (stripos($parser->getCleanFrom(), 'destinationhotels.com') !== false
            || $this->http->XPath->query("//a[contains(@href, '.destinationhotels.com/')]")->length > 0
        ) {
            $email->setProviderCode('dhotels');
        }
        $this->parseEmail($email);
        $email->setType('Reminder' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'hyatt.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailProviders()
    {
        return ['goldpassport', 'dhotels'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $h = $email->add()->hotel();

        // General
        // confirmation
        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))} or starts-with(normalize-space(),'Confirmation Number')]",
            null, true, "/^((.+?)?\s*[:：#]\s*[#\/]?\s*([A-Z\d]{4,}[-\d]*))/u");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))} or starts-with(normalize-space(),'Confirmation Number')]/following::text()[normalize-space()][1]/ancestor::*[position() < 3][{$this->starts($this->t('Confirmation Number'))} or starts-with(normalize-space(),'Confirmation Number')][1]",
                null, true, "/^((.+?)?\s*[:：#]\s*[#\/]?\s*([A-Z\d]{4,}[-\d]*))/u");
        }

        if ($confNo && preg_match("/^(.+?)?\s*[:：#]\s*[#\/]?\s*([A-Z\d]{4,}[-\d]*)/u", $confNo, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        } elseif (empty($confNo)) {
            $confNoArray = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Confirmation Number'))} or starts-with(normalize-space(),'Confirmation Number')]"));

            foreach ($confNoArray as $cn) {
                if (preg_match("/^(.+?)?\s*[:：#]\s*[#\/]?\s*([A-Z\d]{4,}[-\d]*)/u", $cn, $m)) {
                    $h->general()->confirmation($m[2], $m[1]);
                }
            }
        }

        // traveller
        $guestName = $this->nextText($this->t('Guest Name')) ?? $this->nextText('Guest Name');

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation Number') or {$this->contains($this->t('Confirmation Number'))} 
                or {$this->contains($this->t('Cancellation Number'))} or {$this->contains('Cancellation Number')}]/ancestor::*[1]/preceding-sibling::text()[normalize-space(.)!=''][1]");
        }

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation Number') or {$this->contains($this->t('Confirmation Number'))} 
            or {$this->contains($this->t('Cancellation Number'))} or {$this->contains('Cancellation Number')}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)!=''][1]");
        }

        if (!empty($guestName)) {
            foreach ($email->getItineraries() as $key => $value) {
                $h->general()->traveller($guestName);
            }
        }

        // status, cancelled
        if ($cancelNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Number'))}]", null, true, "/{$this->opt($this->t('Cancellation Number'))}\s*[:：]?[\s]?\#?(.*)/")) {
            $h->general()
                ->status('cancelled')
                ->cancellationNumber($cancelNo)
                ->cancelled()
            ;

            if (empty($h->getConfirmationNumbers())) {
                $h->general()
                    ->noConfirmation(true);
            }
        } elseif ($status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation is'))} or starts-with(normalize-space(),'Your reservation has')]", null, true, "/(?:{$this->opt($this->t('Your reservation is'))}|Your reservation has)[:\s]*([\w\s]+)[.!]*$/u")) {
            $h->general()->status($status);
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelled Text")) . "])[1]"))) {
            $h->general()
                ->status('cancelled')
                ->cancelled();
        }

        // Program
        $membership = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership'))}]", null, true, "/[#:]+\s*([A-Z\d]{7,}|[*]{4,}[A-Z\d]{4,})$/");

        if ($membership) {
            $h->program()->account($membership, preg_match("/^[*]+/", $membership) > 0);
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//img[contains(@src, 'images_HY_icon_pin-mask') or contains(@src, 'base/location.png')]/preceding::text()[normalize-space()][1]");

        if ($this->hNameIsValid($hotelName) !== true) {
            $nameArray = array_unique($this->http->FindNodes("//img[contains(@src, 'images_HY_icon_pin-mask') or contains(@src, 'base/location.png')]/preceding::text()[normalize-space()][1]"));

            if (count($nameArray) == 1) {
                $hotelName = $nameArray[0];
            }
        }
        $welcomeTable = "//*[following-sibling::*[normalize-space()][1][{$this->starts($this->t('Everything you need to know'))}]]"
            . "[count(.//td[not(.//td)]) = 5 and descendant::td[not(.//td)][4][.//text()[{$this->eq($this->t('checkIn'))}]] "
            . "and descendant::td[not(.//td)][5][.//text()[{$this->eq($this->t('checkOut'))}]] ]";
        $welcomeType = false;

        if (empty($hotelName) && !empty($this->http->FindSingleNode($welcomeTable))) {
            // it-296593846
            $welcomeType = true;
            $hotelName = $this->http->FindSingleNode($welcomeTable . "/descendant::td[not(.//td)][1]");
            $address = $this->http->FindSingleNode($welcomeTable . "/descendant::td[not(.//td)][2]");
            $phone = $this->http->FindSingleNode($welcomeTable . "/descendant::td[not(.//td)][3]");
        }

        if ($this->hNameIsValid($hotelName) !== true) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))} or starts-with(normalize-space(),'Confirmation Number')][following::text()[{$this->contains($this->t('checkIn'))}]]/
                following::td[not(.//img) and contains(@style,'background')][string-length(normalize-space(.))>0][1][not(contains(normalize-space(), 'Learn'))]");
        }

        if (($this->hNameIsValid($hotelName) !== true || strlen($hotelName) > 100) && $this->http->XPath->query("//img[contains(@alt, 'Image removed by sender')]")->length > 0) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Check-In']/preceding::img[contains(@alt, 'Image removed by sender')][2]/preceding::text()[normalize-space()][1]");
        }

        if ($this->hNameIsValid($hotelName) !== true) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkIn'))}]/preceding::text()[normalize-space()][1][contains(normalize-space(), '+')]/preceding::img[2]/preceding::text()[normalize-space()][1]");
        }

        if ($this->hNameIsValid($hotelName) !== true) {
            $hotelName = $this->nextText($this->t('Cancellation Number'));
        }

        if (stripos($hotelName, 'See Your Benefits >>') !== false) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='See Your Benefits >>']/following::text()[normalize-space()][1]");
        }

        if ($this->hNameIsValid($hotelName) !== true) {
            $hotelName = null;
        }

        $h->hotel()->name($hotelName);

        if (empty($address)) {
            $address = implode(" ", $this->http->FindNodes("//img[contains(@src,'icon_pin') or contains(@src,'39469_img0') or contains(@src,'base/location.png')]/following::text()[normalize-space(.)][1]/ancestor::table[1]//text()[normalize-space(.)]"));
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkIn'))}]/preceding::text()[normalize-space()][1][contains(normalize-space(), '+')]/preceding::img[1]/preceding::text()[normalize-space()][1]/ancestor::td[1]");
        }

        if (empty($phone)) {
            $phone = implode(" ", $this->http->FindNodes("//img[contains(@src,'icon_phone')  or contains(@src,'39469_img1') or contains(@src, 'base/phone.png')]/following::text()[normalize-space(.)][1]/ancestor::table[1]//text()[normalize-space(.)]"));
        }

        if (empty($address) && empty($phone)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkIn'))}]/ancestor::tr[1]/preceding::*[normalize-space(.)][position()<16][(contains(normalize-space(.),\", \") or .//img) and not(contains(normalize-space(.), \"+\"))][1]");
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('checkIn'))}]/ancestor::tr[1]/preceding::*[normalize-space(.)][position()<15][contains(.,\"+\")][1]");
        }

        if (!preg_match("#^\s*([+\d\-\s()]{5,})\s*$#", $phone)) {
            $phone = re('/([+]\d+[-]\d+[-]\d+[-]\d+)/', $phone);
        }
        $h->hotel()
            ->address($address)
            ->phone($phone, false, true);

        // Booked
        $adults = $this->nextText($this->t('Number of Adults'), true) ?? $this->nextText('Number of Adults', true);

        if ($adults == null) {
            $adultsArray = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Number of Adults'))]/following::text()[string-length(normalize-space(.))>0][1]");

            if (count($adultsArray) > 1) {
                $adults = array_sum($adultsArray);
            }
        }
        $h->booked()->guests($adults, false, true);

        $kids = $this->nextText($this->t('Number of Children'), true) ?? $this->nextText('Number of Children', true);

        if ($kids == null) {
            $kidsArray = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Number of Children'))]/following::text()[string-length(normalize-space(.))>0][1]");

            if (count($kidsArray) > 1) {
                $kids = array_sum($kidsArray);
            }
        }
        $h->booked()->kids($kids, false, true);

        $roomsBooked = $this->nextText($this->t('Room(s) Booked'), true) ?? $this->nextText('Room(s) Booked', true);

        // checkInDate
        $dateCheckIn = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}][following::text()[normalize-space()][position() < 10][{$this->eq($this->t('checkOut'))}]])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Date'))}]", null, true, "#:[\s:]+(.+)#");

        if (empty($dateCheckIn)) {
            $dateCheckIn = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}][following::text()[normalize-space()][position() < 10][{$this->eq($this->t('checkOut'))}]])[1]/following::text()[{$this->contains($this->t('Date'))}][1]/ancestor::td[1]", null, true, "#[:\s]+(.+)#");
        }

        $timeCheckIn = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}][following::text()[normalize-space()][position() < 10][{$this->eq($this->t('checkOut'))}]])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Time'))} or contains(.,'Time')]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?[\s]?(.+)/u");

        if (empty($timeCheckIn)) {
            $timeCheckIn = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}][following::text()[normalize-space()][position() < 10][{$this->eq($this->t('checkOut'))}]])[1]/following::text()[{$this->contains($this->t('Time'))} or contains(.,'Time')][1]/ancestor::td[1]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?[\s]?(.+)/u");
        }

        if ($welcomeType === true) {
            $date = $this->http->FindSingleNode($welcomeTable . "/descendant::td[not(.//td)][4]");

            if (preg_match("/{$this->opt($this->t('checkIn'))}[:\s]*{$this->opt($this->t('Date'))}[:\s]*(.+?)\s*(?:{$this->opt($this->t('Time'))}|Time)[:\s]*(.+?)\s*$/u", $date, $m)) {
                $dateCheckIn = $m[1];
                $timeCheckIn = $m[2];
            }
        }

        // checkOutDate
        $dateCheckOut = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkOut'))}])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Date'))}]", null, true, "#:[\s:]+(.+)#");

        if (empty($dateCheckOut)) {
            $dateCheckOut = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkOut'))}])[1]/following::text()[{$this->contains($this->t('Date'))}][1]/ancestor::td[1]", null, true, "#[:：\s]+(.+)#");
        }

        $timeCheckOut = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkOut'))}])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Time'))} or contains(.,'Time')]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?[\s]?(.+)/u");

        if (empty($timeCheckOut)) {
            $timeCheckOut = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('checkOut')) . "])[1]/following::text()[{$this->contains($this->t('Time'))} or contains(.,'Time')][1]/ancestor::td[1]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?[\s]?(.+)/u");
        }

        if ($welcomeType === true) {
            $date = $this->http->FindSingleNode($welcomeTable . "/descendant::td[not(.//td)][5]");

            if (preg_match("/{$this->opt($this->t('checkOut'))}[:\s]*{$this->opt($this->t('Date'))}[:\s]*(.+?)\s*(?:{$this->opt($this->t('Time'))}|Time)[:\s]*(.+?)\s*$/u", $date, $m)) {
                $dateCheckOut = $m[1];
                $timeCheckOut = $m[2];
            }
        }

        // Date: Monday, February 03, 2020 Time:02:00 PM Add to Calendar Check-out Date: Wednesday, February 05, 2020 Time:11:00 AM
        if (preg_match('/Date:(.+?) Time:(.+?) Add to Calendar Check-out Date:(.+?) Time:(.+)/', $dateCheckIn, $m)) {
            $dateCheckIn = $m[1];
            $timeCheckIn = $m[2];
            $dateCheckOut = $m[3];
            $timeCheckOut = $m[4];
        }

        $multipleDates = false;

        if (!preg_match("/\d{4}/", $dateCheckIn)) {
            $multipleDates = true;
        } else {
            if ($dateCheckIn === $dateCheckOut && $this->http->XPath->query("//text()[{$this->contains($this->t('Type of Rate'))}]/following::text()[normalize-space()][position() < 6]"
                    . "[{$this->contains(['Available for guests who require a room between 10:00am and 6:00pm.'])}]")) {
                $timeCheckIn = '10:00';
                $timeCheckOut = '18:00';
            }

            if (empty($timeCheckIn) && !empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}][following::text()[normalize-space()][position() < 10][{$this->eq($this->t('checkOut'))}]])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Time'))} or contains(.,'Time')]/ancestor::*[.//text()[{$this->eq($this->t('checkOut'))}]][1]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?\s*$/u"))) {
                // no time in email
                $timeCheckIn = '00:00';
            }

            if (empty($timeCheckOut) && !empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkOut'))}])[1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[{$this->contains($this->t('Time'))} or contains(.,'Time')]/ancestor::*[.//text()[{$this->eq($this->t('checkOut'))}]][1]", null, true, "/(?:{$this->opt($this->t('Time'))}|Time)[：:]?\s*$/u"))) {
                // no time in email
                $timeCheckOut = '00:00';
            }

            $h->booked()
                ->checkIn2($this->normalizeDate((!empty($dateCheckIn) && !empty($timeCheckIn)) ? $dateCheckIn . ' ' . trim($timeCheckIn, ':') : null))
                ->checkOut2($this->normalizeDate((!empty($dateCheckOut) && !empty($timeCheckOut)) ? $dateCheckOut . ' ' . trim($timeCheckOut, ':') : null));
        }

        if ($roomsBooked == null) {
            $roomsBooked = $this->http->FindNodes("//text()[(starts-with(normalize-space(.),'Room(s) Booked'))]/following::text()[string-length(normalize-space(.))>0][1]");

            if (count($roomsBooked) > 1) {
                $roomsBooked = array_sum($roomsBooked);
            } else {
                $roomsBooked = null;
            }
        }

        $rooms = [];
        $rXpath = "text()[{$this->starts($this->t('Room Type'))} or starts-with(normalize-space(), 'Room Type')]";
        $roomsNodes = $this->http->XPath->query('//' . $rXpath);

        foreach ($roomsNodes as $ri => $rRoot) {
            $roomsText = implode(",", array_filter($this->http->FindNodes("following::text()[normalize-space()][count(preceding::{$rXpath}) = " . ($ri + 1) . "]", $rRoot)));

            $roomXpath = "following::text()[normalize-space()][count(preceding::{$rXpath}) = " . ($ri + 1) . "]";

            $type = $this->http->FindSingleNode($roomXpath . "[string-length(normalize-space(.))>2][1]", $rRoot, true, "/^(?:\s*-\s*)?(.+)/");
            $typeDescriptions = $this->http->FindSingleNode($roomXpath . "[{$this->starts($this->t('Room Description'))} or starts-with(normalize-space(), 'Room Description')]/following::text()[string-length(normalize-space(.))>2][1]", $rRoot, true, "#^(?:\s*-\s*)?(.+)#u");

            $rateType = $this->http->FindSingleNode('(./' . $roomXpath . "[{$this->starts($this->t('Type of Rate'))} or starts-with(normalize-space(), 'Type of Rate')])[1]/following::text()[string-length(normalize-space(.))>2][1]", $rRoot);
            $cancellationPolicies = $this->http->FindSingleNode($roomXpath . "[{$this->starts($this->t('CANCELLATION POLICY'))} or starts-with(normalize-space(), 'CANCELLATION POLICY')]/following::text()[string-length(normalize-space(.))>2][1]", $rRoot);

            $ratesNodes = $this->http->XPath->query($roomXpath . "[{$this->starts($this->t('Nightly Rate per Room:'))} or starts-with(normalize-space(),'Nightly Rate per Room:')]/following::text()[normalize-space()][position() < 20]", $rRoot);
            $rates = [];
            $rateRows = [];

            foreach ($ratesNodes as $rRate) {
                $value = $this->http->FindSingleNode("self::text()[not(ancestor::b) and not(ancestor::b)]", $rRate, true, "/.+ [\-\–\-] .+/u");

                if (empty($value) || preg_match("/.+:\s*$/", $value)) {
                    break;
                }
                $rateRows[] = $value;
            }
            $freeNight = 0;
            $dateFormat = '\w+[.,]?\s+\w+[.]?';

            foreach ($rateRows as $row) {
                if (preg_match("/^\s*({$dateFormat})\s+[\-\–\-]\s+({$dateFormat})\s+[\-\–\-]\s+(.+)/u", $row, $m)) {
                    $date1 = strtotime($this->normalizeDate($m[1] . ' ' . date("Y", $this->date)));
                    $date2 = strtotime($this->normalizeDate($m[2] . ' ' . date("Y", $this->date)));

                    $rdate = $date1;

                    if ($date2 > $date1) {
                        $i = 0;

                        while ($rdate <= $date2 && $i < 20) {
                            $rates[$rdate] = $m[3];
                            $rdate = strtotime("+1 day", $rdate);

                            if (preg_match("/^\D*0[., 0]*\D*$/", $m[3])) {
                                $freeNight++;
                            }
                        }
                    }
                } elseif (preg_match("/^\s*({$dateFormat})\s+[\-\–\-]\s+(.+)/u", $row, $m)) {
                    if (preg_match("/^\D*0[., 0]*\D*$/", $m[2])) {
                        $freeNight++;
                    }

                    $date1 = strtotime($this->normalizeDate($m[1] . ' ' . date("Y", $this->date)));
                    $rates[$date1] = $m[2];
                } else {
                    $rates = [];
                    $this->logger->debug('TO DO: add this case');
                    $h->addRoom();

                    break;
                }
            }

            $rooms[] = [
                'type'                 => $type,
                'typeDescriptions'     => $typeDescriptions,
                'rates'                => $rates,
                'freeNight'            => $freeNight,
                'rateType'             => $rateType,
                'cancellationPolicies' => $cancellationPolicies,
            ];
        }

        if ($roomsBooked == null) {
            foreach ($rooms as $r) {
                $room = $h->addRoom();

                $room
                    ->setType($r['type'])
                    ->setDescription($r['typeDescriptions'], true, true)
                    ->setRateType($r['rateType'], true, true);
            }

            if (count($rooms) == 1) {
                $room
                    ->setRates($r['rates']);
            }

            $freeNight = array_sum(array_column($rooms, 'freeNight'));

            if (!empty($freeNight)) {
                $h->booked()->freeNights($freeNight);
            }
        } elseif ($multipleDates == false
            && ($roomsBooked == 1 && !empty($rooms)
            || $roomsBooked == 2 && count($rooms) === 1)
        ) {
            $h->booked()->rooms($roomsBooked);

            $room = $h->addRoom();

            $room
                ->setType(implode('; ', array_unique(array_column($rooms, 'type'))))
                ->setDescription(implode('; ', array_unique(array_column($rooms, 'typeDescriptions'))), true, true)
                ->setRateType(implode('; ', array_unique(array_column($rooms, 'rateType'))), true, true)
            ;

            $freeNight = array_sum(array_column($rooms, 'freeNight'));

            if (!empty($freeNight)) {
                $h->booked()->freeNights($freeNight);
            }

            $allRates = [];

            foreach ($rooms as $r) {
                if (!array_intersect_key($allRates, $r['rates'])) {
                    $allRates = array_merge($allRates, $r['rates']);
                } else {
                    $allRates = [];

                    break;
                }
            }
            ksort($allRates);

            if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                $dateDiff = date_diff(new \DateTime(date("j F Y", $h->getCheckInDate())), new \DateTime(date("j F Y", $h->getCheckOutDate())))->days;

                if ($dateDiff === count($allRates)) {
                    $room->setRates($allRates);
                } else {
                    $this->logger->debug('the number of rates does not match the number of nights');
                }
            }

            $h->general()
                ->cancellation(implode('; ', array_unique(array_column($rooms, 'cancellationPolicies'))));

            $this->detectDeadLine($h);
        } elseif ($multipleDates == false && !empty($rooms) && $roomsBooked == count($rooms)
        ) {
            $h->booked()->rooms($roomsBooked);

            $freeNight = array_sum(array_column($rooms, 'freeNight'));

            if (!empty($freeNight)) {
                $h->booked()->freeNights($freeNight);
            }

            $h->general()
                ->cancellation(implode('; ', array_unique(array_column($rooms, 'cancellationPolicies'))));

            $this->detectDeadLine($h);

            foreach ($rooms as $r) {
                $room = $h->addRoom();

                $room
                    ->setType($r['type'])
                    ->setDescription($r['typeDescriptions'], true, true)
                    ->setRateType($r['rateType'], true, true);

                if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                    $dateDiff = date_diff(new \DateTime(date("j F Y", $h->getCheckInDate())),
                        new \DateTime(date("j F Y", $h->getCheckOutDate())))->days;

                    if ($dateDiff === count($r['rates'])) {
                        $room->setRates($r['rates']);
                    } else {
                        $this->logger->debug('the number of rates does not match the number of nights');
                    }
                }
            }
        } elseif ($roomsNodes->length == 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('Nightly Rate per Room:'))} or starts-with(normalize-space(),'Nightly Rate per Room:') or {$this->starts($this->t('Type of Rate'))} or starts-with(normalize-space(), 'Type of Rate')]")->length === 0
        ) {
            if (!empty($roomsBooked)) {
                $h->booked()
                    ->rooms($roomsBooked);
            }
        } elseif ($multipleDates == true && ($roomsBooked == 1 || $roomsBooked == count($rooms))) {
            $error = false;

            foreach ($rooms as $i => $r) {
                if (empty($r['rates'])) {
                    $error = true;
                }
            }

            if ($error === false) {
                $h->booked()->rooms($roomsBooked);

                $hotelInfo = $h->toArray();

                foreach ($rooms as $i => $r) {
                    if ($i !== 0) {
                        $h = $email->add()->hotel();

                        $h->fromArray($hotelInfo);
                    }

                    $room = $h->addRoom();

                    $room
                        ->setType($r['type'])
                        ->setDescription($r['typeDescriptions'], true, true)
                        ->setRateType($r['rateType'], true, true);

                    $h->booked()
                        ->checkIn(array_key_first($r['rates']))
                        ->checkOut(strtotime("+ 1 day", array_key_last($r['rates'])));

                    if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                        $dateDiff = date_diff(new \DateTime(date("j F Y", $h->getCheckInDate())),
                            new \DateTime(date("j F Y", $h->getCheckOutDate())))->days;

                        if ($dateDiff === count($r['rates'])) {
                            $room->setRates($r['rates']);
                        } else {
                            $this->logger->debug('the number of rates does not match the number of nights');
                        }
                    }

                    if (!empty($r['freeNight'])) {
                        $h->booked()->freeNights($r['freeNight']);
                    }

                    $h->general()
                        ->cancellation($r['cancellationPolicies']);

                    $this->detectDeadLine($h);
                }
            }
        } elseif ($multipleDates == false && $roomsBooked > 0 && count($rooms) === 1) {
            $h->booked()->rooms($roomsBooked);

            $freeNight = array_sum(array_column($rooms, 'freeNight'));

            if (!empty($freeNight)) {
                $h->booked()->freeNights($freeNight);
            }

            $h->general()
                ->cancellation(implode('; ', array_unique(array_column($rooms, 'cancellationPolicies'))));

            $this->detectDeadLine($h);

            $r = $rooms[0];

            for ($ir = 0; $ir < $roomsBooked; $ir++) {
                $room = $h->addRoom();

                $room
                    ->setType($r['type'])
                    ->setDescription($r['typeDescriptions'], true, true)
                    ->setRateType($r['rateType'], true, true);

                if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                    $dateDiff = date_diff(new \DateTime(date("j F Y", $h->getCheckInDate())),
                        new \DateTime(date("j F Y", $h->getCheckOutDate())))->days;

                    if ($dateDiff === count($r['rates'])) {
                        $room->setRates($r['rates']);
                    } else {
                        $this->logger->debug('the number of rates does not match the number of nights');
                    }
                }
            }
        } else {
            $this->logger->debug('TO DO: add this case');
            $h->addRoom();
        }

        //Price
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Stay Amount:')]/following::text()[1]", null, true, '/^\s?(\D+)\d[,. \d]*\s*\*?\s*$/');
        $tax = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tax Amount:')]/following::text()[1]", null, true, '/^\s?\D+(\d[,. \d]*)\s*\*?\s*$/');
        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Stay Amount:')]/following::text()[1]", null, true, '/^\s?\D+(\d[,. \d]*)\s*\*?\s*$/');

        if ($tax) {
            $email->price()
                ->currency($this->normalizeCurrency($currency))
                ->tax($this->normalizeAmount($tax));
        }

        if ($total) {
            $email->price()
                ->currency($this->normalizeCurrency($currency))
                ->total($this->normalizeAmount($total));
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (
            preg_match("/^CANCEL (?<prior>\d{1,3})\s*DAYS\s*PRIOR TO ARRIVAL TO AVOID DEPOSIT OF \d NIGHTS/i", $h->getCancellation(), $m) // en
            || preg_match("/^(?<prior>\d{1,3})\s*DAYS\s*PRIOR TO ARRIVAL TO AVOID \d+NT FEE/i", $h->getCancellation(), $m) // en
            || preg_match("/^도착 (?<prior>\d{1,3})일 전 취소 시 \d+박 요금 수수료 면제/iu", $h->getCancellation(), $m) // ko
        ) {
            // 7 DAYS PRIOR TO ARRIVAL TO AVOID 1NT FEE
            // 도착 21일 전 취소 시 2박 요금 수수료 면제
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        } elseif (preg_match("/^于入住前\s*(?<prior>\d{1,3})\s*小时取消预订以避免支付 1 晚费用/iu", $h->getCancellation(), $m) // zh
            || preg_match("/체크인\s(?<prior>\d{1,3})시간 전 취소 시 1박 요금 수수료 면제/iu", $h->getCancellation(), $m) // zh
            || preg_match("/^(?<prior>\d{1,3})\s*STUNDEN VOR ANREISE ODER GEBÜHR IN HÖHE DER KOSTEN FÜR \d+ NACHT/iu", $h->getCancellation(), $m) // de
            || preg_match('/CANCEL (?<prior>\d{1,3})\s*HRS PRIOR TO CHECKIN TIME/i', $h->getCancellation(), $m) // en
            || preg_match('/\b(?<prior>\d{1,3})\s*HRS PRIOR TO CHECKIN TO AVOID 1NT FEE/i', $h->getCancellation(), $m) // en
            || preg_match('/\b(?<prior>\d{1,3})\s*HOURS PRIOR TO CHECKIN TO AVOID ONE NIGHT FEE/i', $h->getCancellation(), $m) // en
            || preg_match('/\b(?<prior>\d{1,3})\s*HRS PRIOR OR 1 NIGHT FEE\/ CREDIT CARD REQ/i', $h->getCancellation(), $m) // en
            || preg_match('/(?<prior>\d{1,3})\s*HOURS PRIOR OR 1NIGHT FEE:CREDIT CARD REQ/i', $h->getCancellation(), $m) // en
        ) {
            // 于入住前 48 小时取消预订以避免支付 1 晚费用    |    체크인 48시간 전 취소 시 1박 요금 수수료 면제
            $h->booked()->deadlineRelative($m['prior'] . ' hours', '00:00');
        } elseif (preg_match("/全額を前払いでお支払いいただきます。 変更 、返金はいたしかねます。/iu", $h->getCancellation(), $m) // zh
        ) {
            //체크인 48시간 전 취소 시 1박 요금 수수료 면제
            $h->booked()->parseNonRefundable('全額を前払いでお支払いいただきます。 変更 、返金はいたしかねます。');
        } elseif (preg_match('/TO AVOID \d+NT FEE CANCEL BY (?<prior>\d{1,2}\s*[AP]M) HOTEL TIME DAY OF ARRIVAL/i', $h->getCancellation(), $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        } elseif (preg_match('/TO AVOID \d+ NIGHT FEE CANCEL (\d+)HRS PRIOR TO CHECKIN TIME/i', $h->getCancellation(), $m)
            || preg_match('/(\d+)HRS PRIOR TO CHECK IN TO AVOID \d+NT FEE/i', $h->getCancellation(), $m)
            || preg_match("/Annulations au moins (\d+) heures avant l'heure d'enregistrement/i", $h->getCancellation(), $m)
            || preg_match("/CANCELAR ATÉ (\d+) HORAS ANTES DO CHECK-IN PARA EVITAR MULTA DE UMA DIÁRIA/i", $h->getCancellation(), $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' hours');
        } elseif (preg_match('/CXL BY (?<hour>\d+)(?<apm>[AP]M) HOTEL TIME (?<prior>\d+)HRS PRIOR TO ARRIVAL TO AVOID 1NT PENALTY/i', $h->getCancellation(), $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' hours', $m['hour'] . ':00' . $m['apm']);
        }
    }

    private function hNameIsValid(?string $hotelName): bool
    {
        if (empty($hotelName)) {
            return false;
        }

        return stripos($this->subjectValue, $hotelName) !== false
            || preg_match('/(?:\bHYATT\b|\bRESORTS?\b|\bAND SPA\b|凯悦|하얏트|호텔|Lindner)/iu', $hotelName) > 0
            || $this->http->XPath->query("//text()[{$this->contains($hotelName)}]")->length > 1
        ;
    }

    private function nextText($field, $fl = false): ?string
    {
        if ($fl) {
            return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/following::text()[string-length(normalize-space(.))>0 and not(normalize-space()=':')][1]");
        }

        return $this->http->FindSingleNode("(//text()[{$this->starts($field)}]/following::text()[string-length(normalize-space(.))>2][1])[1]");
    }

    private function nextTexts($field, $regexp = null): array
    {
        return array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($field)}]/following::text()[string-length(normalize-space())>2][1]", null, $regexp)));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (
                $this->http->XPath->query("//*[" . $this->contains($reBody[0]) . "]")->length > 0
                && $this->http->XPath->query("//*[" . $this->contains($reBody[1]) . "]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        // Wednesday, January 24, 2024 4:00 p.m. at the Woodrun Place front desk
        $date = preg_replace("/^(.+\d{1,2}:\d{2}\s*[ap] *\. *m *\.) at .+/iu", '$1', $date);
        // $this->logger->debug('$date in = '.print_r( $date,true));
        // kostyl for zh
        //2019年10月2日 上午 12:00 - not midnight - midday
        $date = str_replace('上午 12:00', '下午 12:00', $date);
        $in = [
            // 1: Sunday, April 26, 2020 10:00 AM or 11:00 AM*
            '/^\w+[,]\s*(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*([\d:]+\s?(?:AM|PM))\s*(?:or|to)\s*[\d:]+\s?(?:AM|PM)[*]?$/ui',
            '#\w+,\s+(\w+)\s+(\d+),\s+(\d+)\s+(\d+)[:\.](\d+\s*[ap]\.?m\.?)#ui',
            '#\w+,\s+(\w+)\s+(\d+),\s+(\d+)\s+(\d+)\s*([ap]\.?m\.?)#ui',
            '#\w+,\s+(\w+)\s+(\d+),\s+(\d+)\s+(\d+)\s*(noon)#ui',
            '#\w+,\s+(\d+)\.\s+(\w+)\s+(\d{4})\s+(\d+:\d+\s*(?:[AP]M)?)\s*(?:Uhr)?#ui',
            '#^\w+,\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s+(\d+:\d+\s*[APM\.]+)\s*$#ui',
            // samedi, 6 juin 2020 03:00 PM
            '#^\w+,\s+(\d+)\s+(\w+)\s+(\d{4})\s+(\d+:\d+\s*[APM\.]+)\s*$#ui',

            // 8: 星期一, 2018年11月25日 2:00 P.M.
            '#^[^\d\W]+[,\s]+(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$#u',
            // 일요일, 2019년 7월 14일 04:00 PM
            '#^[^\d\W]+[,\s]+(\d{4})년\s*(\d{1,2})월\s*(\d{1,2})일\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$#u',
            '#^\s*(\d{1,2})\s*-\s*([^\d\s]+)\s*-\s*(\d{4})#ui', //17-Mar-2019
            '/^\w+, (\d{1,2}) de (\w+) de (\d{2,4}) (\d{1,2}:\d{2})$/iu', // martes, 19 de marzo de 2019 15:00
            // Segunda-feira, 15 de Julho de 2019 03:00 PM
            '/^.+?,? (\d+) de (\w+) de (\d+) (\d+:\d+\s*[AP]M)$/iu',

            // 13: 2019年10月1日 下午 3:00
            '/^(\d{4})年(\d{1,2})月(\d{1,2})日[ ]+下午[ ]+(\d+:\d+)$/',
            //2019年10月2日 上午 12:00
            '/^(\d{4})年(\d{1,2})月(\d{1,2})日[ ]+上午[ ]+(\d+:\d+)$/',

            //2019年11月14日 午後3時
            '/^(\d{4})年(\d{1,2})月(\d{1,2})日\s午後(\d+)時$/',

            //2019年11月15日 午前11時
            '/^(\d{4})年(\d{1,2})月(\d{1,2})日\s午前(\d+)時$/',

            //2020年1月3日 (金曜日) 03:00 PM
            '/^(\d{4})年(\d{1,2})月(\d{1,2})日\s\D+(\d+.\d+\s\S+)$/',

            '/^(\d{4})年\s*(\d{1,2})月\s*(\d{1,2})日\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/',
            //星期日 2020年5月31日
            '/\w+,?\s+(\d{4})年(\d+)月(\d+)日/u',

            // 20:  abril 12 2022
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s+(\d{4})\s*$/u',
            //1月 11日 2019
            '/^\s*(\d+)\s*(?:月|월)\s*(\d+)\s*(?:日|일)\s*(\d{4})\s*$/u',
            // Monday, 24-Jan-2022 12:00 PM
            '#^\s*\w+,\s*(\d{1,2})\s*-\s*([^\d\s]+)\s*-\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)$#ui',
            // martes, 21 de mayo de 2024
            '#^\w+,\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s*$#ui',
            // 일요일, 2019년 7월 14일
            '#^[^\d\W]+[,\s]+(\d{4})년\s*(\d{1,2})월\s*(\d{1,2})일\s*$#u',
        ];
        $out = [
            // 1
            '$2 $1 $3, $4',
            '$1 $2 $3 $4:$5',
            '$1 $2 $3 $4:00 $5',
            '$1 $2 $3 $4:00 pm',
            '$1 $2 $3 $4',
            '$1 $2 $3 $4',
            '$1 $2 $3, $4',

            // 8
            '$2/$3/$1 $4',
            '$2/$3/$1 $4',
            '$1 $2 $3',
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',

            // 13
            '$1-$2-$3, $4PM',
            '$1-$2-$3, $4AM',
            '$1-$2-$3, $4PM',
            '$1-$2-$3, $4AM',
            '$1-$2-$3, $4',
            '$3-$2-$1, $4',
            '$3-$2-$1',

            // 20
            '$2 $1 $3',
            '$3-$1-$2',
            '$1 $2 $3, $4',
            '$1 $2 $3',
            '$1-$2-$3',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("/^(.+) ((\d{1,2}):\d{2})\s*[ap]m\s*$/i", $str, $m)
            && $m[3] > 12
        ) {
            // 18:00 PM -> 18:00 PM
            $str = $m[1] . ' ' . $m[2];
        }

        if (preg_match('# ([[:alpha:]]+) #iu', $str, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
//                $this->logger->debug('OUT ' . $str);

                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
            } elseif (!empty($this->dateLang) && $this->dateLang !== $this->lang && $translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->dateLang)) {
//                $this->logger->debug('OUT ' . $str);
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
            } else {
                // lang of the date does not match the lang of email
                if (preg_match("/^\s*([[:alpha:]\-]+)\s*,/u", $date, $mweek)) {
                    foreach (array_keys(self::$dict) as $lang) {
                        $translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $lang);
                        $translatedWeekName = \AwardWallet\Engine\WeekTranslate::translate($mweek[1], $lang);

                        if (!empty($translatedMonthName) && !empty($translatedWeekName)) {
                            $this->dateLang = $lang;

                            return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
                        }
                    }
                } else {
                    foreach (array_keys(self::$dict) as $lang) {
                        $translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $lang);

                        if (!empty($translatedMonthName)) {
                            $this->dateLang = $lang;

                            return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
                        }
                    }
                }
            }
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeCurrency($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
        ];
        $string = trim($string);

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
