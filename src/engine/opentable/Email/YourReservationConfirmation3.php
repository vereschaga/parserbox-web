<?php

namespace AwardWallet\Engine\opentable\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationConfirmation3 extends \TAccountChecker
{
    public $mailFiles = "opentable/it-101586090.eml, opentable/it-124264486.eml, opentable/it-12638613.eml, opentable/it-14127780.eml, opentable/it-14127790.eml, opentable/it-33956277.eml, opentable/it-346129306.eml, opentable/it-62217150.eml, opentable/it-63573075.eml, opentable/it-6563265.eml, opentable/it-686257018.eml, opentable/it-8907811.eml";

    public static $dictionary = [
        "en" => [
            "Name:"                                                => ["Name:", "Name :"],
            "Confirmation #"                                       => ["Confirmation #", "Reservation #", "Booking #", "Confirmation no.", "Confirmation No."],
            "Table for "                                           => ["Table for ", "Outdoor seating for ", 'Bar seating for', 'Counter seating for ', 'High-top seating for ', 'Rooftop (Outdoor) • Table for ', 'Tickets for ',
                'Lounge • Table for ', ],
            "justEarned"                                           => "You’ve just earned",
            "Your new balance is"                                  => ["Your new balance is", "Your new balance will be"],
            "Reservation confirmed"                                => ["Reservation confirmed", "Booking confirmed"],
            'Reservation canceled'                                 => ['Booking cancelled', 'Reservation canceled', 'reservation was canceled', 'Reservation cancelled', 'reservation has been cancelled',
                'reservation was cancelled', ],
            "We hope you enjoyed your recent dining experience at" => ["We hope you enjoyed your recent dining experience at", "We hope you enjoyed your dining experience."], // when not "Table for"
        ],
        "de" => [ // it-62217150.eml
            "Name:"                                                => ["Reservierungsname:", "Name:"],
            "Confirmation #"                                       => ["Bestätigungsnummer", "Reservierung Nr.", 'Reservierung #'],
            "Table for "                                           => ["Tisch für ", "Außenplätze für "],
            "justEarned"                                           => "Sie haben gerade",
            "Your new balance is"                                  => "Ihr neues Guthaben beträgt",
            "points"                                               => "Punkte",
            "Reservation confirmed"                                => ["Reservierung bestätigt"],
            'Reservation canceled'                                 => ['Reservierung storniert'],
            "Get directions"                                       => ["Anfahrt anzeigen"],
            "We hope you enjoyed your recent dining experience at" => ["Wir hoffen, Ihnen hat Ihr letzter Restaurantbesuch gefallen."], // when not "Table for"
        ],
        "es" => [
            "Name:"                 => ["Nombre:"],
            "Confirmation #"        => ["Número de confirmación", "Reservación #", "Reserva n. °"],
            "Table for "            => ["Mesa para", "Asientos al aire libre para", 'Asientos en el mostrador para ', 'Patio • Asientos al aire libre para ',
                'Bar • Asientos al aire libre para ', 'Asientos en el bar para', 'Asientos en mesa alta para', ],
            "justEarned"            => "Acaba de obtener",
            // "Your new balance is" => "",
            "points"                                               => "puntos",
            "Reservation confirmed"                                => ["Reservación confirmada"],
            'Reservation canceled'                                 => ['Reservación cancelada', 'Cancelaste correctamente tu reservación en'],
            "Get directions"                                       => ["Obtener indicaciones", "Cómo llegar"],
            "We hope you enjoyed your recent dining experience at" => "Valoramos la retroalimetación acerca de su experiencia en",
        ],
        "fr" => [ // it-63573075.eml
            "Name:"               => ["Réservation au nom de:", 'Nom:'],
            "Confirmation #"      => ["Numéro de confirmation", "Réservation Nº", "Confirmation numéro"],
            "Table for "          => ["Table pour ", 'Places extérieures pour '],
            "justEarned"          => "Vous cumulerez",
            "Your new balance is" => "Votre nouveau solde sera de",
            "points"              => "points",
            //            "Reservation confirmed" => [""],
            'Reservation canceled'                                 => 'Réservation annulée',
            "Get directions"                                       => ["Obtenir l'itinéraire"],
            "We hope you enjoyed your recent dining experience at" => "Nous espérons que vous avez apprécié votre expérience culinaire", // when not "Table for"
        ],
        "nl" => [
            "Name:"          => ["Naam:"],
            "Confirmation #" => ["Bevestigingsnummer"],
            "Table for "     => ["Tafel voor", 'Zitplaatsen buiten voor'],
            // "justEarned" => "",
            // "Your new balance is" => "",
            // "points" => "",
            "Reservation confirmed" => ["Reservering bevestigd"],
            //            'Reservation canceled' => '',
            "Get directions"                                       => ["Routebeschrijving"],
            //            "We hope you enjoyed your recent dining experience at" => "", // when not "Table for"
        ],
        "ja" => [
            "Name:"          => ["ご予約氏名: ", '名前:'],
            "Confirmation #" => ["ご予約確認番号", 'ご予約番号', 'ご予約番号:'],
            "Table for "     => ["テーブル［"],
            "justEarned"     => "ポイントが加算されました！",
            // "Your new balance is" => "ポイント残高は",
            "points" => "ポイン",
            //                        "Reservation confirmed" => [""],
            //            'Reservation canceled' => '',
            "Get directions"                                       => ["ルートを見る"],
            "We hope you enjoyed your recent dining experience at" => "お客様のご感想をお聞かせいただければ幸いです。", // when not "Table for"
        ],
        "it" => [
            "Name:"                 => ["Nome:"],
            "Confirmation #"        => ["Conferma n.:", "Prenotazione n."],
            "Table for "            => ["Tavolo per"],
            "justEarned"            => "Dopo la tua visita al ristorante guadagnerai",
            "Your new balance is"   => "Il tuo nuovo saldo sarà di",
            "points"                => "punti",
            "Reservation confirmed" => ["Prenotazione confermata"],
            //            'Reservation canceled' => '',
            "Get directions" => ["Ottieni le indicazioni stradali"],
            //            "We hope you enjoyed your recent dining experience at" => "", // when not "Table for"
        ],
        "zh" => [
            "Name:"                 => ["姓名:"],
            "Confirmation #"        => ["確認編號", '訂位編號'],
            "Table for "            => ["分的"],
            //"justEarned"            => "Dopo la tua visita al ristorante guadagnerai",
            //"Your new balance is"   => "Il tuo nuovo saldo sarà di",
            "points"                => "punti",
            //"Reservation confirmed" => ["Prenotazione confermata"],
            'Reservation canceled'                                 => '你已成功取消以下訂位',
            "Get directions"                                       => ["取得路線指引"],
            "We hope you enjoyed your recent dining experience at" => [
                "若你能對這家餐廳的體驗留下意見，我們會十分感激：",
                "新的詳細資料如下。", ], // when not "Table for"
        ],
    ];

    public $lang = '';

    private $reSubject = [
        // en
        "en"  => "Your Reservation Confirmation for",
        "en2" => "reservation has been canceled",
        "en3" => "Invitation to ",
        "en4" => "Your booking confirmation for",
        "en5" => "reservation was canceled",
        "en6" => "Your seated confirmation for dining at",
        "en7" => "Reservation Change",
        'Your booking cancellation for ',
        'has invited you to dine at',
        ' updated your ',
        'booking has been cancelled',
        // de
        'de'  => 'Bestätigung Ihrer Reservierung im',
        'Stornierung Ihrer Reservierung:',
        'Ihre Platzbestätigung für das Essen im Restaurant',
        ' Die Stornierung Ihrer Reservierung bei ',
        // es
        'es'  => 'Confirmación de su reservación en', 'Confirmación de reserva en',
        'es2' => ' que asistirá mañana', 'Confirmación de tus puntos para comer en',
        'es3' => 'Cambio de su reservación en',
        'es4' => 'Cancelación de su reservación en',
        'La cancelación de su reservación para',
        '¿Cómo estuvo ',
        'Cambio de tu reservación en ',
        // fr
        'fr'  => 'que vous serez là demain',
        'fr2' => 'Voici la confirmation que vous avez bel et bien mangé au',
        'fr3' => 'Comment avez-vous trouvé le restaurant',
        'Annulation de votre réservation au restaurant:',
        'Modification de votre réservation au restaurant',
        'Confirmation de réservation pour',
        // nl
        'Wijziging in uw reservering bij',
        'Uw reserveringsbevestiging bij',
        // ja
        'ご予約内容の確認',
        'でのお食事はいかがでしたか？',
        'ご来店の記録【',
        // it
        'La tua conferma di prenotazione da',
    ];

    private $detectors = [
        'en' => [
            'Get directions',
            'for using OpenTable!',
            'Make a new reservation',
            'Make a new booking',
            'We would appreciate your feedbac',
            'Here are a few messages from the restaurant',
            'Thank you for dining with us!',
            'If you have questions about your canceled reservation, please contact',
            'If you have questions about your cancelled reservation, please contact',
            'Had your heart set on this restaurant?',
            'Your booking has been cancelled by the restaurant',
            'Thank you for choosing Gyu-Kaku',
            'You’ve successfully been refunded',
        ],
        'de' => [
            'Anfahrt anzeigen',
            'Wir freuen uns auf Ihr Feedback',
            'Vielen Dank für Ihren Besuch',
            'Ihre Reservierung wurde storniert',
            'Sie haben erfolgreich die folgende Reservierung storniert',
        ],
        'es' => [
            'Reserva confirmada',
            'Reservación confirmada',
            'Su reservación es mañana.',
            'Se modificó la reservación',
            'Ha cancelado con éxito su reservación',
            'Gracias por usar OpenTable.',
            'Gracias por cenar con nosotros.',
            'Cancelaste correctamente tu reservación en',
            'Tu reservación',
            'Se modificó la reserva',
        ],
        'fr' => [
            'votre réservation est demain.', 'Votre réservation est confirmée',
            "Merci d’avoir mangé chez nous!", "Merci d'avoir mangé chez nous!",
            'Merci d’avoir utilisé OpenTable.', "Merci d'avoir utilisé OpenTable.",
            'Nous apprécierions recevoir vos commentaires',
            'Vous avez annulé votre réservation avec succès au',
            'Voici les nouvelles informations détaillées.',
            'Nous espérons vous revoir très bientôt.',
            'Réservation confirmée',
        ],
        'nl' => [
            'Routebeschrijving',
        ],
        'ja' => [
            'ルートを見る',
            'お食事はいかがでしたか',
            '同じお店に予約を入れる',
        ],
        'it' => [
            'Ottieni le indicazioni stradali',
            'OpenTable e il logo di OpenTable sono marchi registrati',
        ],
        'zh' => ['感謝你使用OpenTable',
            '若你能對這家餐廳的體驗留下意見，我們會十分感激：',
            'OpenTable上再次見到你',
            '希望你滿意最近的用餐體驗：',
            '新的詳細資料如下。',
            '你的訂位就在明天。',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->http->FilterHTML = true;

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@opentable.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".opentable.com") or contains(@href,"components.otstatic.com")]')->length === 0
            && $this->http->XPath->query('//node()[
            contains(normalize-space(),"OpenTable, Inc. - All rights reserved") 
            or contains(normalize-space(),"OpenTable, Inc. - Alle Rechte vorbehalten") 
            or contains(normalize-space(),"registered trademarks of OpenTable") 
            or contains(.,"@opentable.com") 
            or contains(.,"community.opentable.com")
            or contains(.,"components.otstatic.com")
            ]')->length === 0
        ) {
            return false;
        }

        $this->logger->error('YES');

        return $this->detectBody() && $this->assignLang();
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
        $patterns = [
            'travellerName' => '[[:alpha:]][-,.\'’\(\)\/[:alpha:] ]*[[:alpha:]][.),]?', // Syvalia Hyman, IV    |    Kenneth Deneau Jr.  |    Alione (Leo) Lana
        ];

        $event = $email->add()->event();

        // ConfNo
        $confNo = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Confirmation #")) . "])[1]/following::text()[normalize-space()][normalize-space(.) != ':'][1]",
            null, true, "#^[\:]?\s*(\d+)\s*$#u");

        if (preg_match("#\d+#", $confNo) != true) {
            $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation #")) . "][1]",
                null, true, "#:\s*(\d+)#");
        }

        if (empty($confNo) && $this->http->XPath->query("//*[starts-with(text(), 'Name')]/ancestor::td[contains(.,'Name:')][1]/following::td[normalize-space(.)!=''][1][.//a]")->length > 0) {//after Name td with href
            $event->general()
                ->noConfirmation();
        } elseif (empty($confNo) && $this->http->XPath->query("//text()[contains(normalize-space(.), 'reserved a table at')]")->length > 0) {
            $event->general()
                ->noConfirmation();
        } elseif (empty($confNo) && $this->http->XPath->query("//text()[{$this->starts($this->t('Confirmation #'))}]")->length === 0) {
            $event->general()
                ->noConfirmation();
        }

        if (!empty($confNo)) {
            $event->general()
                ->confirmation($confNo);
        }

        $event->setEventType(EVENT_RESTAURANT);

        $xpathFragmentRed = "({$this->contains(['#da3743', '#DA3743', 'rgb(218,55,67)', 'rgb(218, 55, 67)'], '@style')})";

        // Name
        $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))}]/preceding::text()[normalize-space()][1][ ancestor::*[$xpathFragmentRed] ]");

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("We hope you enjoyed your recent dining experience at")) . "]/following::text()[normalize-space()][1]");
        }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))}]/preceding::text()[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))}]/preceding::text()[normalize-space()][1][ancestor::a[contains(@href,'opentable.com')]]")
                ?? $this->http->FindSingleNode("//a[(@id='restaurant-name' or @id='restaurant-name-url') and contains(@href,'opentable.com')]")
            ;
        }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->contains(preg_replace("/^(.)/", ' • $1', $this->t("Table for ")))}]/preceding::text()[normalize-space()][1][ ancestor::*[$xpathFragmentRed] ]");
        }

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[{$this->contains(preg_replace("/^(.)/", ' • $1', $this->t("Table for ")))}]/preceding::text()[normalize-space()][1][ancestor::a[count(.//text()[normalize-space()]) = 1]]");
        }

        if (!empty($eventName)) {
            $event->place()
                ->name($eventName);
        }

        // StartDate
        $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))} and following::tr[{$this->starts($this->t('Name:'))} or {$this->starts($this->t('First Name:'))}]]/ancestor::tr[1]", null, true, "# (?:\d+ on|para \d+|\d+ pour|Person\(en\) am|\d+ il giorno|\d+ op),? (.+)#")));

        if (empty($startsDate)) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//a[@id='restaurant-name' or @id='restaurant-name-url' or @id='restaurant-profile-url']/following::text()[normalize-space(.)][1]",
                null, true, '/.*?(\w+\W*\w+\W+\w+\W+\w*\W*\d{4}\w*\W*\s+\w+\s*\w*\s+\d{1,2}:\d{2}.*)/u')));
        }

        if (empty($startsDate)) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Table for ")) . "]", null, true,
                "/{$this->opt($this->t("Table for "))}\s*\d+.*? (\w+,.+)/")));
        }

        if (empty($startsDate)) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Table for ")) . "]")));
        }

        if (empty($startsDate)) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//a[@id='restaurant-name' or @id='restaurant-name-url' or @id='restaurant-profile-url']/following::text()[normalize-space(.)][1]")));
        }

        if (empty($startsDate) && !empty($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Table for ")) . "]",
                null, true, '/^\s*' . $this->opt($this->t("Table for ")) . '\s*\d+\s*$/u'))) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Table for ")) . "]/following::text()[normalize-space()][1]",
                null, true, '/^\s*\w+\s+(\w+\W*\w+\W+\w+\W+\d{4}\W*\s+\w+\s+\d{1,2}:\d{2}.*)$/u')));
        }

        if (empty($startsDate)) {
            $startsDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains(preg_replace("/^(.)/", ' • $1', $this->t("Table for ")))}]", null, true,
                "/{$this->opt($this->t("Table for "))}\s*\d+.*? (\w+[, ]+\d{1,2}\b.*)/u")));
        }

        if (!empty($startsDate)) {
            $event->booked()
                ->start($startsDate)
                ->noEnd();
        }

        // Address
        $address = implode(" ",
            $this->http->FindNodes("//text()[normalize-space()='See menu']/following::text()[normalize-space()][not(contains(normalize-space(), 'Get directions') or contains(normalize-space(), '|'))][1]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (empty($address)) {
            $address = implode(" ",
                $this->http->FindNodes("//text()[" . $this->eq($this->t("Get directions")) . "]/ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)]"));
        }

        if (!empty($address)) {
            $event->place()
                ->address($address);
        } else {
            $event->place()
                ->address($event->getName());
        }

        // Phone
        $phone = $this->http->FindSingleNode("//text()[normalize-space()='See menu']/following::text()[normalize-space()][not(contains(normalize-space(), 'Get directions') or contains(normalize-space(), '|'))][1]/ancestor::tr[1]/following::tr[1]", null, true, "/^([\d\s\(\)\-\.]+)/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Get directions")) . "]/ancestor::tr[1]/following-sibling::tr[2]", null, true, "/^([\d\s\(\)\-\.]+)/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->contains("If you have questions about your canceled") . "]",
                null, true, "# at ([\d() -\.]+?)\.$#");
        }

        if (!empty($phone)) {
            $event->place()
                ->phone($phone);
        }

        // DinerName
        $name = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Name:'))} and (preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()])]", null, true,
            "/{$this->opt($this->t('Name:'))}\s*({$patterns['travellerName']})(?: \d{4,}|\s*\.)?$/u"
        );

        if (!$name) {
            $nameParts = [];
            $firstName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("First Name:"))}]", null, true,
                "/{$this->opt($this->t("First Name:"))}\s*({$patterns['travellerName']})$/u");

            if ($firstName) {
                $nameParts[] = $firstName;
            }
            $lastName = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Last Name:"))}]", null, true,
                "/{$this->opt($this->t("Last Name:"))}\s*({$patterns['travellerName']})$/u");

            if ($lastName) {
                $nameParts[] = $lastName;
            }

            if (count($nameParts)) {
                $name = implode(' ', $nameParts);
            }
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->contains(['reserved a table at', 'updated the reservation', 'booked a table at']) . "]", null,
                true, '/(.+) ' . $this->opt(['reserved a table at', 'updated the reservation', 'booked a table at']) . '/');
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Name:'))}]", null, true,
                '/:\s*(.+)/');
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation was canceled'))}]", null, true,
                '/^(\D+)\s+reservation was canceled/');
        }

        if (empty($name)) {
            $title = preg_replace('/\s*:\s*$/', '', $this->t('Name:'));
            $name = $this->http->FindSingleNode("//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($title)}] and descendant::text()[normalize-space()][2][{$this->eq(':')}]]", null, true,
                "/{$this->opt($title)}\s*:\s*({$patterns['travellerName']}(?:\s*&\s*{$patterns['travellerName']})?)$/");
        }

        $event->general()
            ->traveller(trim($name, ','));

        // Guests
        $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))} and following::tr[{$this->starts($this->t('Name:'))} or {$this->starts($this->t('First Name:'))}]]", null, true, "/{$this->opt($this->t("Table for "))}[ ]*(\d{1,3})[ ,]/");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Table for "))} and following::tr[{$this->starts($this->t('Name:'))} or {$this->starts($this->t('First Name:'))}]]", null, true, "/{$this->opt($this->t("Table for "))}[ ]*(\d{1,3})[ ,]*/");
        }

        if (!empty($guests)) {
            $event->booked()
                ->guests($guests);
        }

        // EarnedAwards
        $earnedAwards = $this->http->FindSingleNode("//text()[contains(normalize-space(),'points upon dining')]/ancestor-or-self::node()[contains(normalize-space(),'You will')][1]", null, true, "/You\s+will\s+(?:earn|collect)\s+(\d[,.\'\d ]*\s+points?)\s+upon\s+dining/") // it-6563265.eml
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t("justEarned"))}]/ancestor-or-self::node()[contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')][1]", null, true, "/{$this->opt($this->t("justEarned"))}\s+(\d[,.\'\d ]*\s+{$this->opt($this->t("points"))})/") // it-101586090.eml
        ;

        if (!empty($earnedAwards)) {
            $event->ota()
                ->earnedAwards($earnedAwards);
        }

        // Status
        if ($this->http->XPath->query("//text()[{$this->starts($this->t("Reservation canceled"))}]")->length >= 1
            || $this->http->FindSingleNode("//text()[{$this->starts($this->t("Table for "))}]/ancestor::td[1]/preceding::td[not(.//td)][normalize-space()][{$this->contains($this->t('Reservation canceled'))}]")
        ) {
            $event->general()
                ->cancelled()
                ->status("cancelled");
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t("Reservation confirmed"))}]")->length > 0) {
            $event->general()
                ->status("confirmed");
        }

        // it-101586090.eml
        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Your new balance is"))}]/ancestor-or-self::node()[contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')][1]", null, true, "/{$this->opt($this->t("Your new balance is"))}\s+(\d[,.\'\d ]*)\s+{$this->opt($this->t("points"))}/");

        if ($balance !== null) {
            $st = $email->add()->statement();
            $st->setBalance($this->normalizeAmount($balance));
            $st->addProperty('Name', $name);
        }
    }

    private function detectBody(): bool
    {
        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Name:']) || empty($phrases['Confirmation #'])) {
                continue;
            }

            if (($this->http->XPath->query("//node()[{$this->contains($phrases['Name:'])}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($phrases['Table for '])}]")->length > 0)
                && ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation #'])}]")->length > 0)
            ) {
                $this->lang = $lang;

                return true;
            }

            if (empty($this->lang)) {
                foreach (self::$dictionary as $lang => $phrases) {
                    $detects = [];

                    foreach ($phrases as $ph) {
                        if (is_string($ph)) {
                            $detects[] = $ph;
                        } elseif (is_array($ph)) {
                            $detects = array_merge($detects, $ph);
                        }
                    }

                    if (!empty($detects) && $this->http->XPath->query("//text()[{$this->contains($detects)}]")->length > 2) {
                        $this->lang = $lang;

                        return true;
                    }
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
        $this->logger->debug("Date before: " . $str);
        //$year = date("Y", $this->date);
        $in = [
            //Sunday, May 14, 2017 at 7:15 pma
            "#\s*\w*\s*[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
            //Saturday, 26 May 2018 at 8:00 pm
            // Venerdì, 25 febbraio 2022 alle 19:45
            "#[^\d\s]+,?\s+(\d+)\s+(?:de )?([^\d\s]+)\s+(?:de )?(\d{4})\s+(?:at|en|alle)\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
            // Tisch für 2 Person(en) am Sonntag, 28. Juli 2019, um 19:30 Uhr.
            "/^.+?(\d{1,2})\. (\w+ \d{4}), \w+ (\d+:\d+).*$/u",
            // jeudi 13 août 2020 à 19:00
            "/^\s*(?:.+?\b)?(\d{1,2} \w+ \d{4}) à (\d+:\d+)\s*$/u",
            //el sábado 24 de julio de 2021 a las 20:45
            '/el [-[:alpha:]]+\s*(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s+a las\s+(\d{1,2}:\d{2})$/u',

            // May 14, 2017 at 7:15 pma
            "#^\s*([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
            // 26 May 2018 at 8:00 pm;  1. September 2021 um 12:15
            "#^\s*(\d+)[.]?\s+(?:de )?([^\d\s]+)\s+(?:de )?(\d{4})\s+(?:at|en|om|um)\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
            // 4名様］2022年1月2日（日）07:30
            "/^.*\b(\d{4})年(\d{1,2})月(\d{1,2})日\D*(\d{1,2}:\d{2})\s*$/",
            // sábado 1 de abril de 2023 a las 14:00
            "/^\s*\w*\,?\s+(\d+)\.?\s*\w*\s+(\w+)\s+\w*\s*(\d{4})\s*\D+(\d+\:\d+)$/u",
            //於2023年4月10日（星期一）晚上9點00分的2人座位
            "/^[於]*(\d+)[年](\d+)[月](\d+)[日]\D+(\d+)[點](\d+).*$/u",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2, $3",
            "$1, $2",
            "$1 $2 $3, $4",

            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1-$2-$3, $4",
            "$1 $2 $3, $4",
            "$3.$2.$1, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);
//         $this->logger->debug("Date after: " . $str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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
}
