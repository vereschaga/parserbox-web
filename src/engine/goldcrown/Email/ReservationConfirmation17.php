<?php

namespace AwardWallet\Engine\goldcrown\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation17 extends \TAccountChecker
{
    public $mailFiles = "goldcrown/it-131756708-fr.eml, goldcrown/it-133045282-da.eml, goldcrown/it-134844044-pl.eml, goldcrown/it-148686274.eml, goldcrown/it-33807555.eml, goldcrown/it-33823249.eml, goldcrown/it-37053016.eml, goldcrown/it-62599060.eml, goldcrown/it-9775989.eml, goldcrown/it-9790747.eml, goldcrown/it-98363692.eml, goldcrown/it-9853724.eml";

    public static $dictionary = [
        'de' => [
            "Guest"                            => ["Gast", "Gäste"],
            "Room"                             => "Zimmer",
            "Room "                            => "Zimmer ",
            'Room ∆'                           => 'Zimmer ∆',
            'Cancellation Policy'              => 'Stornierungsbedingung',
            'Reservation Confirmation Number'  => ['Reservierungs-Bestätigungsnummer', 'Bestätigungsnummer'],
            'Cancellation Number'              => 'Stornierungsnummer',
            'Location'                         => 'Ort',
            'Checkin'                          => 'Anreise',
            'Checkout'                         => 'Abreise',
            'Hotel Number'                     => 'Hotelnummer',
            'Reservation Amount'               => 'Reservierungsbetrag',
            'Other Taxes & Fees'               => 'Andere Steuern und Gebühren',
            'Total Stay Amount'                => 'Gesamtaufenthalt',
            // 'Reservation Points' => '',
            'status'       => 'Ihre Reservierung ist',
            'statusRegExp' => 'Ihre Reservierung ist (.+?)(?:\.|$)',
            //			'cancelledRegExp' => '',
            'Member' => 'Mitglied',
        ],
        'it' => [ // it-33823249.eml
            "Guest"                           => ["Clienti", "Cliente"],
            "Room"                            => "Camera",
            'Room ∆'                          => 'Camera ∆',
            'Cancellation Policy'             => 'Politica di cancellazione',
            'Reservation Confirmation Number' => ['Numero di conferma della prenotazione', 'Numero di conferma', 'Conferma n.'],
            'Cancellation Number'             => 'Numero di cancellazione',
            'Location'                        => 'Località',
            'Checkin'                         => ['Check-in', 'Arrivo'],
            'Checkout'                        => ['Checkout', 'Partenza'],
            'Hotel Number'                    => 'Numero dell’hotel',
            'Reservation Amount'              => 'Importo della prenotazione',
            'Other Taxes & Fees'              => 'Altri supplementi e tasse',
            'Total Stay Amount'               => 'Soggiorno totale',
            // 'Reservation Points' => '',
            'status'       => 'La tua prenotazione è stata',
            'statusRegExp' => 'La tua prenotazione è stata (.+?)(?:\.|$)',
            //			'cancelledRegExp' => '',
            'Member' => 'Membro',
        ],
        'es' => [
            "Guest"                           => ["Huésped", "Huéspedes"],
            "Room"                            => "Habitación",
            'Room ∆'                          => 'Habitación ∆',
            'Cancellation Policy'             => 'Política de cancelaciones',
            'Reservation Confirmation Number' => ['Número de confirmación de reserva', 'Codigo de confirmación:'],
            // 'Cancellation Number' => '',
            'Location'                        => 'Ubicación',
            'Checkin'                         => 'Llegada',
            'Checkout'                        => 'Salida',
            'Hotel Number'                    => 'Número de hotel',
            'Reservation Amount'              => 'Cantidad de la reserva',
            'Other Taxes & Fees'              => 'Otros impuestos y tasas',
            'Total Stay Amount'               => 'Estancia total',
            'Reservation Points'              => ['Puntos de la reserva', 'Puntos'],
            'status'                          => 'Se ha confirmado',
            'statusRegExp'                    => 'Se ha (.+?) su reserva$',
            //			'cancelledRegExp' => '',
            'Member' => 'Miembro',
        ],
        'fr' => [ // it-62599060.eml, it-131756708-fr.eml
            "Guest"                           => ["Client", "Clients", "Adultes"],
            "Room"                            => "Chambre",
            'Room ∆'                          => 'Chambre ∆',
            // 'Cancellation Policy' => '',
            'Reservation Confirmation Number' => ['Numéro de confirmation', 'Numéro de confirmation de la réservation'],
            'Cancellation Number'             => "Numéro d’annulation",
            'Location'                        => 'Emplacement',
            'Checkin'                         => 'Arrivée',
            'Checkout'                        => 'Départ',
            'Hotel Number'                    => ['Numéro de l’hôtel'],
            'Reservation Amount'              => 'Montant de la réservation',
            'Other Taxes & Fees'              => 'Autres taxes et frais',
            'Total Stay Amount'               => 'Total séjour',
            'Reservation Points'              => 'Points de réservation',
            //            'status' => 'Se ha confirmado',
            'statusRegExp' => 'Numéro de (.+?) de la réservation$',
            //			'cancelledRegExp' => '',
            'Member' => 'Membre',
            'Hotel'  => 'Hôtel', //use for collection phone only
        ],
        'da' => [ // it-133045282-da.eml
            'Guest'                           => 'Gæster',
            'Room'                            => 'værelse',
            'Room ∆'                          => 'værelse ∆',
            // 'Cancellation Policy' => '',
            'Reservation Confirmation Number' => 'Reservationsbekræftelsesnummer',
            // 'Cancellation Number' => '',
            'Location'                        => 'Beliggenhed',
            'Checkin'                         => 'Indtjekning',
            'Checkout'                        => 'Udtjekning',
            'Hotel Number'                    => 'Hotellets direkte nummer',
            'Reservation Amount'              => 'Reservationsbeløb',
            'Other Taxes & Fees'              => 'Andre afgifter & gebyrer',
            'Total Stay Amount'               => 'Total ophold',
            // 'Reservation Points' => '',
            'status'       => 'Din reservation er',
            'statusRegExp' => 'Din reservation er (bekræftet)(?:\s*[,.:;!?]|$)',
            // 'cancelledRegExp' => '',
            'Member' => 'Medlem',
        ],
        'pl' => [ // it-134844044-pl.eml
            'Guest'                           => 'Goście',
            'Room'                            => 'Pokój',
            'Room ∆'                          => 'Pokój ∆',
            // 'Cancellation Policy' => '',
            'Reservation Confirmation Number' => 'Numer potwierdzenia rezerwacji',
            // 'Cancellation Number' => '',
            'Location'                        => 'Położenie',
            'Checkin'                         => 'Zameldowanie',
            'Checkout'                        => 'Wymeldowanie',
            'Hotel Number'                    => 'Numer hotelu',
            // 'Reservation Amount'              => '',
            // 'Other Taxes & Fees'              => '',
            // 'Total Stay Amount'               => '',
            'Reservation Points' => 'Ilość punktów do rezerwacji',
            'status'             => 'Państwa rezerwacja jest',
            'statusRegExp'       => 'Państwa rezerwacja jest (potwierdzona)(?:\s*[,.:;!?]|$)',
            // 'cancelledRegExp' => '',
            'Member' => 'Członek',
        ],
        'sv' => [ // it-98363692.eml
            'Guest'                           => ['Gäst', 'Gäster'],
            'Room'                            => 'Rum',
            'Room ∆'                          => 'Rum ∆',
            // 'Cancellation Policy' => '',
            'Reservation Confirmation Number' => 'Bokningsnummer',
            'Cancellation Number'             => 'Avbokningsnummer',
            'Location'                        => 'Adress',
            'Checkin'                         => 'Incheckning',
            'Checkout'                        => 'Utcheckning',
            'Hotel Number'                    => 'Hotellnummer',
            'Reservation Amount'              => 'Reservations information',
            'Other Taxes & Fees'              => 'Andra skatter & avgifter',
            'Total Stay Amount'               => 'Totalt pris',
            // 'Reservation Points' => '',
            'status'          => 'Din bokning är',
            'statusRegExp'    => 'Din bokning är(?:\s+nu)? (bekräftad|avbokad)(?:\s*[,.:;!?]|$)',
            'cancelledRegExp' => '^(Avbokning bekräftad)(?:\.|$)',
            'Member'          => 'Medlem',
        ],
        'en' => [
            'Guest'                           => ['Guest', 'Guests'],
            'Room'                            => ['Room', 'Rooms'],
            'Reservation Confirmation Number' => ['Reservation Confirmation Number', 'Confirmation Number', 'Confirmation Number'],
            'Location'                        => 'Location',
            'Total Stay Amount'               => ['Total Stay Amount', 'Total Stay'],
            'status'                          => ['Your reservation has been successfully', 'Your Reservation is', 'YOUR RESERVATION IS'],
            'statusRegExp'                    => '(?:Your reservation has been successfully|Your Reservation is|YOUR RESERVATION IS) (.+?)(?:\.|$)',
            'cancelledRegExp'                 => '^CANCELLED$',
            'Checkin'                         => ['Checkin', 'CHECK-IN', 'ARRIVAL', '办理入住手续'],
            'Checkout'                        => ['Checkout', 'CHECK-OUT', 'DEPARTURE', '办理退房手续'],
            //            'Member' => 'Member',
            'Maximum Occupancy' => ['Maximum Occupancy', '最多住客人数'],
            'Adult'             => ['Adult', '成年人'],
            // 'Child' => '',
        ],
    ];

    public $lang = "";
    private $enDatesInverted = false;

    private $reSubject = [
        'de' => ['Reservierungsbestätigung', 'Stornierungsbestätigung'],
        'it' => ['Conferma della prenotazione', ' Conferma della cancellazione'],
        'es' => ['Confirmación de reserva'],
        'fr' => ['Confirmation de réservation', "Confirmation d’annulation", 'Votre prochain séjour à'],
        'da' => ['Reservation bekræftelse'],
        'pl' => ['Potwierdzenie rezerwacji'],
        'sv' => ['Bokning bekräftad', 'Avbokning bekräftad'],
        'en' => ['Reservation Confirmation', 'Cancellation Confirmation', 'Your upcoming trip to', 'Check-in Now for Your Arrival'],
    ];

    private $detectors = [
        'de' => ['Ihre Reservierung ist bestätigt.', 'Hotelnummer'],
        'it' => ['La tua prenotazione è stata', 'Numero dell’hotel', 'Non vediamo l’ora di darti il benvenuto'],
        'es' => ['Se ha confirmado su reserva', '¡Esperamos verle pronto'],
        'fr' => ['Votre réservation est', 'Numéro de confirmation de la réservation', "Numéro d’annulation", " Politique de respect de la vie privée"],
        'da' => ['Din reservation er bekræftet'],
        'pl' => ['Państwa rezerwacja jest potwierdzona'],
        'sv' => ['Bokningsnummer'],
        'en' => ['Your Reservation is', 'We look forward seeing you soon',
            'We look forward to seeing you soon', 'Your reservation has been successfully canceled',
            'Each Best Western® branded hotel is independently owned and operated', '', ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Best Western Hotel') !== false
            || preg_match('/[.@]bestwestern\.(?:com|dk|fi|se)/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.bestwestern.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Best Western Hotels & Resorts") or contains(normalize-space(),"Best Western International, Inc") or contains(.,".bestwestern.com")]')->length === 0
            && !empty($parser->getHTMLBody())
        ) {
            return false;
        }

        return $this->detectBody($parser->getPlainBody());
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        if (empty($parser->getHTMLBody()) && !empty($parser->getPlainBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

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
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon|[ ]*nachm)?',
            'travellerName' => '(?:[A-z][-.&\'A-z ]*[A-z]|\w+ [\w\-]+)', // John & Kelly Swertfager
        ];

        $r = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Confirmation Number'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()] ][1]/descendant::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//img[contains(@src, 'brandLogos/Logo_BWR')]/following-sibling::node()[normalize-space(.)][1]",
                null, true, "/^{$patterns['travellerName']}$/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'We look forward to seeing you soon')]",
                null, true, "/^We look forward to seeing you soon\s*(\D+)/");
        }

        if ($traveller) {
            $travellers = preg_split('/\s*&\s*/', $traveller);
            $r->general()->travellers($travellers);
        }

        // Programm
        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation Confirmation Number'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()] ][1]/descendant::text()[normalize-space()][2]", null, true, "/.+{$this->opt($this->t("Member"))}\s*:\s*(\W{3,}\d{4})\s*$/iu"); // it-62599060.eml

        if (empty($account)) {
            $accountValues = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("Member"))}]", null, "/{$this->opt($this->t("Member"))}\s*[:]+\s*([-A-Z\d]{5,})$/i"));

            if (count(array_unique($accountValues)) === 1) {
                $account = array_shift($accountValues);
            }
        }

        if (!empty($account)) {
            $r->program()->account($account, preg_match('/^[-\w]+$/', $account) !== 1);
        }

        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Reservation Confirmation Number"))}]/preceding::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Reservation Confirmation Number"))}]", null, true, '/\s*[A-Z\d]{5,}$/');
        }

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Reservation Confirmation Number"))}]");

            if (empty($confirmationNumberTitle)) {
                $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Reservation Confirmation Number"))}]", null, true, "/^({$this->opt($this->t('Reservation Confirmation Number'))})/");
            }
            $r->general()->confirmation($confirmationNumber, rtrim($confirmationNumberTitle, ' :'));
        }

        $cancellationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Cancellation Number"))}]/preceding::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($cancellationNumber)) {
            $cancellationNumberArray = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Cancellation Number:')]", null, "/{$this->opt($this->t('Cancellation Number:'))}\s*([A-Z\d]{5,})/"));

            if (count($cancellationNumberArray) == 1) {
                $cancellationNumber = $cancellationNumberArray[0];
            }
        }

        if ($cancellationNumber) {
            $cancellationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Cancellation Number"))}]");
            $r->general()->cancellationNumber($cancellationNumber, rtrim($cancellationNumberTitle, ' :'), true);
        }

        $hotelName = '';
        $hotelAdress = '';
        $hotelPhone = '';

        if ($this->http->XPath->query("//text()[{$this->eq($this->t("Location"))}]")->length > 0) {
            $locationHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Location"))}]/ancestor::td[1]");
            $locationText = $this->htmlToText($locationHtml);

            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Location"))}]/preceding::text()[normalize-space()][not(ancestor::a)][1]");
            $hotelAdress = preg_replace('/\s+/', ' ', preg_replace("/^\s*{$this->opt($this->t("Location"))}\s*/", '', $locationText));
            $hotelPhone = $this->nextText($this->t("Hotel Number"));
        } else {
            $locationHtml = $this->http->FindHTMLByXpath("//img[contains(@src, 'Location_Icon')]/ancestor::td[1]/following-sibling::td[1]");
            $locationText = $this->htmlToText($locationHtml);

            $hotelName = $this->http->FindSingleNode("//img[contains(@src,'Location_Icon')]/preceding::text()[normalize-space()][not(ancestor::a)][1]");
            $hotelAdress = preg_replace('/\s+/', ' ', preg_replace("/^\s*{$this->opt($this->t("Location"))}\s*/", '', $locationText));
            $hotelPhone = $this->http->FindSingleNode("//img[contains(@src, 'Phone_Icon')]/following::text()[normalize-space(.)][not({$this->contains($this->t('Hotel'))})][1]");
        }

        $r->hotel()
            ->name($hotelName)
            ->address($hotelAdress)
            ->phone($hotelPhone);

        if ($this->lang === 'pl') {
            $this->enDatesInverted = true;
        } elseif (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $this->http->Response['body'], $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = true;

                    break;
                }
            }
        }

        $xpathDatesOuter = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Checkin"))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t("Checkout"))}] ]/*[normalize-space()]";
        $xpathDatesInner = "/descendant::div[ div[normalize-space()]/following-sibling::div[normalize-space()='']/following-sibling::div[normalize-space()] ][1]/div[normalize-space()]";

        // checkInDate
        $checkInTexts = [];
        $checkInRows = $this->http->XPath->query($xpathDatesOuter . '[1]' . $xpathDatesInner); // it-148686274.eml

        if ($checkInRows->length === 0) {
            $checkInRows = $this->http->XPath->query($xpathDatesOuter . '[1]'); // it-37053016.eml
        }

        foreach ($checkInRows as $row) {
            $checkInTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $row));
        }
        $checkInText = implode("\n", $checkInTexts);
        $checkInParts = preg_split('/[ ]*\n+[ ]*/', $checkInText);

        if (count($checkInParts) > 2) {
            $timeCheckIn = $this->normalizeTime($checkInParts[1]);
            $dateCheckInVal = count($checkInParts) === 5
                ? $checkInParts[3] . ' ' . $checkInParts[4] // it-148686274.eml
                : $checkInParts[2] // it-37053016.eml
            ;

            if (strlen($dateCheckInVal) <= 2 && count($checkInParts) === 4) { //if collection day only
                $dateCheckInVal .= ' ' . $checkInParts[3];
            }
            $dateCheckIn = $this->normalizeDate($dateCheckInVal);
            $r->booked()->checkIn(strtotime($timeCheckIn, strtotime($dateCheckIn)));
        } elseif (strpos($checkInText, ':') === false) {
            $dateCheckIn = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'ARRIVAL')]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'ARRIVAL'))]"));

            if (empty($dateCheckIn)) {
                $dateTextIn = $this->http->FindSingleNode("//img[contains(@alt, 'CHECK-IN')]/@alt");

                if (preg_match("/CHECK-IN\s*(?<time>[\d\:]+\s*A?P?M)\s+\w+\s+(?<date>\d+\s*\w+\s*\d{4})/", $dateTextIn, $m)) {
                    $dateCheckIn = $m['date'] . ' ' . $m['time'];
                }
            }

            $r->booked()->checkIn(strtotime($dateCheckIn));
        }

        // checkOutDate
        $checkOutTexts = [];
        $checkOutRows = $this->http->XPath->query($xpathDatesOuter . '[2]' . $xpathDatesInner);

        if ($checkOutRows->length === 0) {
            $checkOutRows = $this->http->XPath->query($xpathDatesOuter . '[2]');
        }

        foreach ($checkOutRows as $row) {
            $checkOutTexts[] = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $row));
        }
        $checkOutText = implode("\n", $checkOutTexts);
        $checkOutParts = preg_split('/[ ]*\n+[ ]*/', $checkOutText);

        if (count($checkOutParts) > 2) {
            $timeCheckOut = $this->normalizeTime($checkOutParts[1]);
            $dateCheckOutVal = count($checkOutParts) === 5
                ? $checkOutParts[3] . ' ' . $checkOutParts[4]
                : $checkOutParts[2];

            if (strlen($dateCheckOutVal) <= 2 && count($checkOutParts) === 4) {
                $dateCheckOutVal .= ' ' . $checkOutParts[3];
            }

            $dateCheckOut = $this->normalizeDate($dateCheckOutVal);
            $r->booked()->checkOut(strtotime($timeCheckOut, strtotime($dateCheckOut)));
        } elseif (strpos($checkOutText, ':') === false) {
            $dateCheckOut = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'DEPARTURE')]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'DEPARTURE'))]"));

            if (empty($dateCheckOut)) {
                $dateTextOut = $this->http->FindSingleNode("//img[contains(@alt, 'CHECK-OUT')]/@alt");

                if (preg_match("/CHECK-OUT\s*(?<time>[\d\:]+\s*A?P?M)\s+\w+\s+(?<date>\d+\s*\w+\s*\d{4})/", $dateTextOut, $m)) {
                    $dateCheckOut = $m['date'] . ' ' . $m['time'];
                }
            }
            $r->booked()->checkOut(strtotime($dateCheckOut));
        }

        $guests = $this->http->FindSingleNode("//node()[" . $this->eq($this->t("Guest")) . "]/preceding-sibling::node()[normalize-space()][1]", null, true, "/^\d{1,3}$/");

        if (empty($guests)) {
            $guests = array_sum(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Maximum Occupancy'))}]/ancestor::tr[1]/descendant::td[2]", null, "/(\d+)\s*{$this->opt($this->t('Adult'))}/u")));
        }

        if (!empty($guests)) {
            $r->booked()
                ->guests($guests);
        }

        $kids = array_sum(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Maximum Occupancy'))}]/ancestor::tr[1]/descendant::td[2]", null, "/(\d+)\s*{$this->opt($this->t('Child'))}/u")));

        if ($kids !== 0) {
            $r->booked()
                ->kids($kids);
        }

        $rooms = $this->http->FindSingleNode("//node()[" . $this->eq($this->t("Room")) . "]/preceding-sibling::node()[normalize-space()][1]", null, true, "/^\d{1,3}$/");

        if (empty($rooms)) {
            $rooms = max(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Room"))}]/ancestor::td[1]", null, "/^\s*{$this->opt($this->t('Room'))}\s*(\d+)/")));
        }

        if (empty($rooms)) {
            $rooms = max(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Room "))}]", null, "/^\s*{$this->opt($this->t('Room '))}\s*(\d+)/")));
        }

        if (!empty($rooms)) {
            $r->booked()
                ->rooms($rooms);
        }

        $roomDescriptions = $this->http->FindNodes("//text()[{$this->eq($this->t("Room"))}]/ancestor::tr[1]/descendant::td[2]");

        foreach ($roomDescriptions as $roomDescription) {
            $room = $r->addRoom();
            $room->setDescription($roomDescription);
        }

        $totalStayText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Total Stay Amount"))}] ]/*[normalize-space()][2]"));

        if (preg_match('/(?<amount>\d[,.\'\d ]*?)[*\s]+(?<currency>[^\-\d\n)(]+)$/su', $totalStayText, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[*\s]+(?<currency>[^\-\d\n)(]+)/', $totalStayText, $matches)) {
            // 1,104.68 USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $reservationAmount = trim($this->nextText($this->t("Reservation Amount")), '*');

            if ($reservationAmount !== '') {
                $r->price()->cost(PriceHelper::parse($reservationAmount, $currencyCode));
            }

            $taxes = trim($this->nextText($this->t("Other Taxes & Fees")), '*');

            if ($taxes !== '') {
                $r->price()->tax(PriceHelper::parse($taxes, $currencyCode));
            }
        }

        $reservationPoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Reservation Points"))}]/following::text()[normalize-space()][1][not(contains(normalize-space(), '+') or contains(normalize-space(), 'Cantidad de la reserva'))]", null, true, "/^[* ]*(.*\d.*?)[* ]*$/");

        if ($reservationPoints !== null) {
            $r->price()->spentAwards(PriceHelper::parse($reservationPoints) . ' points');
        }

        $statusMatches = array_filter($this->http->FindNodes("descendant::text()[{$this->contains($this->t('status'))}]", null, "/{$this->t('statusRegExp')}/i"));

        if (count(array_unique($statusMatches)) === 1) {
            $r->general()->status(array_shift($statusMatches));
        } elseif (preg_match("/{$this->t('cancelledRegExp')}/i", implode("\n", $statusMatches), $m)) {
            $r->general()
                ->status($m[1])
                ->cancelled();
        }

        if (!empty($r->getCancellationNumber())) {
            // it-37053016.eml
            $r->general()->cancelled();
        }

        $cancellation = null;
        $xpathCancellation = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}] ]/*[normalize-space()][2]";

        $cancellationByRooms = [];
        $cancellationNodes = $this->http->XPath->query($xpathCancellation . "/descendant::*[{$this->starts($this->t('Room ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')} and count(following-sibling::*[normalize-space()])=1 and not(.//div) and not(.//tr)]");

        foreach ($cancellationNodes as $cNode) {
            $cName = $this->http->FindSingleNode('.', $cNode, true, '/^(.+?)[\s:：]*$/u');
            $cValue = $this->http->FindSingleNode('following-sibling::*[normalize-space()]', $cNode, true, '/^(.+?)[\s;.!?]*$/');
            $cancellationByRooms[$cName] = $cValue;
        }

        if (count(array_unique($cancellationByRooms)) === 1) {
            $cancellation = array_shift($cancellationByRooms);
        } elseif (count($cancellationByRooms) > 0) {
            array_walk($cancellationByRooms, function (&$value, $key) {
                $value = $key . ': ' . $value;
            });
            $cancellation = implode('; ', $cancellationByRooms);
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode($xpathCancellation . "[not(descendant::*[{$this->starts($this->t('Room ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}])]");
        }

        if (!empty($cancellation)) {
            $r->general()->cancellation($cancellation);

            if (preg_match("/This (?i)reservation is non-refundable$/", $cancellation)
            || preg_match("/Esta reserva no es reembolsable/", $cancellation)
            || preg_match("/Questa prenotazione non è rimborsabile/", $cancellation)
            ) {
                $r->booked()->nonRefundable();
            } elseif (preg_match("/Cancell? (?i)before (?<time>{$patterns['time']}) hotel time on (?<date>.{2,}?\d{4}) to avoid a charge[.!]*$/", $cancellation, $m)
                || preg_match("/Puede cancelar su reserva sin cargos antes de las (?<time>{$patterns['time']}) hora local del hotel el (?<date>.{2,}?\d{4})/", $cancellation, $m)// es
                || preg_match("/Sie können Ihre Reservierung kostenfrei stornieren bis (?<time>{$patterns['time']}). Ortszeit des Hotels am (?<date>.{2,}?\d{4})/", $cancellation, $m)// es
                || preg_match("/You may cancel your reservation for no charge before (?<time>{$patterns['time']}) local hotel time on (?<date>.{2,}?\d{4})/", $cancellation, $m)// en
            ) {
                $m['time'] = str_replace(['nachm'], 'PM', $m['time']);
                $r->booked()->deadline(strtotime($this->normalizeDate($m['date'] . ' ' . $m['time'])));
            }
        }
    }

    private function detectBody(string $body = ''): bool
    {
        foreach ($this->detectors as $lang => $phrases) {
            if ($this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0) {
                $this->lang = $lang;

                return $this->assignLang();
            } elseif (!empty($body)) {
                foreach ($phrases as $phrase) {
                    if (false !== stripos($body, $phrase)) {
                        $this->lang = $lang;

                        return $this->assignLang($body);
                    }
                }
            }
        }

        return false;
    }

    private function assignLang(string $body = ''): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Reservation Confirmation Number"], $words["Location"])) {
                if (
                    (
                        $this->http->XPath->query("//*[{$this->contains($words['Reservation Confirmation Number'])}]")->length > 0
                        && ($this->http->XPath->query("//*[{$this->contains($words['Location'])}]")->length > 0
                        || $this->http->XPath->query("//img[contains(@src, 'Location_Icon')]")->length > 0)
                    )
                    || (
                        preg_match("/{$this->opt($words['Reservation Confirmation Number'])}/", $body)
                        //false !== stripos($body, $words['Reservation Confirmation Number'])
                        && false !== stripos($body, $words['Location'])
                    )
                ) {
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

    private function normalizeDate($str): string
    {
        //$this->logger->debug($str);
        $in = [
            // 06 NOV 2017    |    06-NOV-2017    |    26 JUIL. 2020
            '/^(\d{1,2})[-\s.]+([[:alpha:]]+)[-\s.]+(\d{4})$/u',
            // 20/06/2021
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
            // Tuesday, April, 05, 2022
            '/^[-[:alpha:]]+[ ]*,[ ]*([[:alpha:]]+)[, ]+(\d{1,2})[ ]*,[ ]*(\d{4})$/u',
            // jueves mayo 04, 2023 04:00 p. m.
            '/^\w+\s*(\w+)\s*(\d+)\,?\s*(\d{4})\s*([\d\:]+)\s*(a?p?)\.?\s*m\.?$/ui',
        ];
        $out[0] = '$1 $2 $3';
        $out[1] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';
        $out[2] = '$2 $1 $3';
        $out[3] = '$2 $1 $3, $4 $5m';
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'zh')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/([AaPp])\.[ ]*([Mm])\.?/', '$1$2', $s); // 2:04 p. m.    ->    2:04 pm

        if (preg_match('/^(?:12|12:00)?\s*noon/i', $s)) {
            // 12:00 NOON CEST
            return '12:00';
        } elseif (preg_match('/^\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?/', $s, $m)) {
            // 3:00 P.M. AKST    |    2:00 pm CEST
            return $m[0];
        }
        $s = str_replace(['nachm'], ['PM'], $s); // 04:00 nachm    ->    04:00 PM

        return $s;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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
}
