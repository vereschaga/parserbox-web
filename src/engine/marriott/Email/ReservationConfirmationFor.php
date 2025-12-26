<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "marriott/it-1670342.eml, marriott/it-1672625.eml, marriott/it-1678205.eml, marriott/it-1681239.eml, marriott/it-1688492.eml, marriott/it-1854298.eml, marriott/it-1855792.eml, marriott/it-2212409.eml, marriott/it-2293594.eml, marriott/it-2297617.eml, marriott/it-2439556.eml, marriott/it-2928014.eml, marriott/it-3022149.eml, marriott/it-3139180.eml, marriott/it-3495376.eml, marriott/it-3983047.eml, marriott/it-4005674.eml, marriott/it-9014134.eml, marriott/it-3793848.eml, marriott/it-3997792.eml";

    public $reSubject = [
        "es" => "Confirmación de la reserva",
        "de" => "Reservierungsbestätigung",
        "pt" => "Confirmação de reserva",
        //		"fr" => "",
        "en" => "Reservation Confirmation",
    ];
    public $reBody = 'Marriott';
    public $reBody2 = [
        "es"  => "Confirmación de la reserva:",
        "de"  => "Reservierungsbestätigung:",
        "pt"  => "Confirmação de reserva:",
        "fr"  => "Nous sommes ravis de confirmer",
        "fr2" => "Votre réservation est confirmée",
        "en"  => "Reservation Confirmation:",
        "en2" => 'Marriott keeps an official record of all electronic reservations',
        "en3" => 'Reservation Confirmation for :',
    ];

    public static $dictionary = [
        "es" => [
            "Reservation Confirmation:" => "Confirmación de la reserva:",
            "Hotel address"             => "NOTTRANSLATED",
            "Telephone number"          => "NOTTRANSLATED",
            "Map & Directions"          => "Mapas e indicaciones",
            "Plan Your Stay"            => "Planifique su estancia",
            "CHECK-IN DATE"             => "FECHA DE LLEGADA",
            "CHECK-IN TIME"             => "HORA DE LLEGADA",
            "CHECK-OUT DATE"            => "FECHA DE SALIDA",
            "CHECK-OUT TIME"            => "HORA DE SALIDA",
            "For "                      => "Para ",
            "REWARDS NUMBER"            => "NÚMERO DE REWARDS",
            "NUMBER OF ROOMS"           => "NÚMERO DE HABITACIONES",
            "GUESTS PER ROOM"           => "HUÉSPEDES POR HABITACIÓN",
            //			"Adult" => "",
            //			"Child" => "",
            "ROOM TYPE"                         => "TIPO DE HABITACIÓN",
            "ESTIMATED GOVERNMENT TAXES & FEES" => "NOTTRANSLATED",
            //			"Summary of Charges" => "",
            "Total for stay (for all rooms)" => "NOTTRANSLATED",
            //			"Rate and Cancellation Details" => "",
            //			"RATE GUARANTEE LIMITATION(S)" => "",
        ],
        "de" => [
            "Reservation Confirmation:" => "Reservierungsbestätigung:",
            "Hotel address"             => "Hoteladresse",
            "Telephone number"          => "Telefonnummer",
            "Map & Directions"          => "Lage und Anfahrt",
            "Plan Your Stay"            => "Planen Sie Ihren Aufenthalt",
            "CHECK-IN DATE"             => "Anreisedatum",
            "CHECK-IN TIME"             => "Ankunftszeit",
            "CHECK-OUT DATE"            => "Abreisedatum",
            "CHECK-OUT TIME"            => "Check-Out",
            "For "                      => "Für ",
            "REWARDS NUMBER"            => "Rewards Nummer",
            "NUMBER OF ROOMS"           => "Anzahl der Zimmer",
            "GUESTS PER ROOM"           => "Gäste pro Zimmer",
            //			"Adult" => "",
            //			"Child" => "",
            "ROOM TYPE"                         => "Zimmertyp",
            "ESTIMATED GOVERNMENT TAXES & FEES" => "Geschätzte anfallende Steuern und Abgaben",
            "Summary of Charges"                => "Gesamtkosten",
            "Total for stay (for all rooms)"    => "Gesamtkosten für den Aufenthalt (alle Zimmer)",
            "Rate and Cancellation Details"     => "Detaillierte Informationen zu Tarifen und Stornierung",
            "RATE GUARANTEE LIMITATION(S)"      => "Preisgarantie-Einschränkung(en)",
        ],
        "pt" => [
            "Reservation Confirmation:" => "Confirmação de reserva:",
            "Hotel address"             => "Endereço do hotel",
            "Telephone number"          => "Número do telefone",
            "Map & Directions"          => "Mapa e como chegar",
            "Plan Your Stay"            => "Planeje a sua estada",
            "CHECK-IN DATE"             => "DATA DA CHEGADA",
            "CHECK-IN TIME"             => "HORÁRIO DE CHECK-IN",
            "CHECK-OUT DATE"            => "DATA DA PARTIDA",
            "CHECK-OUT TIME"            => "HORÁRIO DE CHECK-OUT",
            "For "                      => "Para ",
            //			"REWARDS NUMBER" => "",
            "NUMBER OF ROOMS" => "NÚMERO DE QUARTOS",
            "GUESTS PER ROOM" => "HÓSPEDES POR QUARTO",
            //			"Adult" => "",
            //			"Child" => "",
            "ROOM TYPE"                         => "CATEGORIA DE QUARTO",
            "ESTIMATED GOVERNMENT TAXES & FEES" => "TAXAS E IMPOSTOS GOVERNAMENTAIS ESTIMADOS",
            "Summary of Charges"                => "Resumo das despesas",
            "Total for stay (for all rooms)"    => "Total por estada (para todos os quartos)",
            "Rate and Cancellation Details"     => "Detalhes de tarifa e cancelamento",
            "RATE GUARANTEE LIMITATION(S)"      => "LIMITAÇÕES DE GARANTIA DE TARIFA",
        ],
        "fr" => [
            "Reservation Confirmation:" => "Confirmation de réservation:",
            "Hotel address"             => "Adresse de l'hôtel",
            //			"Telephone number" => "",
            //			"Map & Directions" => "",
            //			"Plan Your Stay" => "",
            "CHECK-IN DATE"  => "DATE D'ARRIVÉE",
            "CHECK-IN TIME"  => "HEURE D'ARRIVÉE",
            "CHECK-OUT DATE" => "DATE DE DÉPART",
            "CHECK-OUT TIME" => "HEURE DE DÉPART",
            "For "           => "Pour ",
            //			"REWARDS NUMBER" => "",
            "NUMBER OF ROOMS" => "NOMBRE DE CHAMBRES",
            "GUESTS PER ROOM" => "NOMBRE D'OCCUPANTS PAR CHAMBRE",
            //			"Adult" => "",
            //			"Child" => "",
            "ROOM TYPE"                         => "TYPE DE CHAMBRE",
            "ESTIMATED GOVERNMENT TAXES & FEES" => "TAXES ET FRAIS GOUVERNEMENTAUX",
            //			"Summary of Charges" => "",
            "Total for stay (for all rooms)" => "Montant total pour le séjour (pour toutes les chambres)",
            //			"Rate and Cancellation Details" => "",
            //			"RATE GUARANTEE LIMITATION(S)" => "",
        ],
        "en" => [
            "Reservation Confirmation:"      => ["Reservation Confirmation:", "Reservation Confirmation for :"],
            "Hotel address"                  => ["Hotel address", "Address"],
            "Plan Your Stay"                 => ["Plan Your Stay", "Plan your Stay"],
            "Summary of Charges"             => ["Summary of Charges", "Summary Of Charges", "SUMMARY OF CHARGES"],
            "Total for stay (for all rooms)" => ["Total for stay (for all rooms)", "Total Stay(for all rooms)"],
            "Rate and Cancellation Details"  => ["Rate and Cancellation Details", "RATE AND CANCELLATION DETAILS"],
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'reservations@courtyard-res.com') !== false
            || stripos($from, 'reservations@proteahotels-res.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, "1234567890");

                break;
            }
        }

        $this->parseHtml($email);

        $email->setType('ReservationConfirmationFor' . ucfirst($this->lang));

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
        $h = $email->add()->hotel();

        // ConfirmationNumber
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Reservation Confirmation:"))}]");

        if (preg_match("/({$this->opt($this->t("Reservation Confirmation:"))})\s*(\d+)$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        } else {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Reservation Confirmation:"))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d+)\s*$/");
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Reservation Confirmation:"))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathFragmentAddressImg = $this->contains(['/ico_map.', '/RCKL1_LOCATOR.'], '@src');
        $xpathFragmentPhoneImg = $this->contains(['/ico_phone.', '/RCKL2_TELEPHONE.'], '@src');

        $xpathFragmentRow = "(self::table or self::tr)";

        // HotelName
        if (!$hotelName = $this->http->FindSingleNode("descendant::img[$xpathFragmentAddressImg or {$this->eq($this->t("Hotel address"), '@alt')}][1]/ancestor::*[$xpathFragmentRow and count(preceding-sibling::*[normalize-space()])>1 ][1]/preceding-sibling::*[$xpathFragmentRow][1]")) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Map & Directions"))}]/ancestor::*[$xpathFragmentRow and count(preceding-sibling::*[normalize-space()])>1 and {$this->contains($this->t("Plan Your Stay"))}][1]/preceding-sibling::*[$xpathFragmentRow and normalize-space()][2]/descendant::text()[normalize-space()][1]");
        }

        $patterns['time'] = '\d{1,2}(?:[h:：]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        // CheckInDate
        // CheckOutDate
        $h->booked()
            ->checkIn2($this->normalizeDate($this->nextText($this->t("CHECK-IN DATE"))) . ', ' . $this->normalizeTime($this->nextText($this->t("CHECK-IN TIME"))))
            ->checkOut2($this->normalizeDate($this->nextText($this->t("CHECK-OUT DATE"))) . ', ' . $this->normalizeTime($this->nextText($this->t("CHECK-OUT TIME"))));

        // Address
        if (!$address = $this->http->FindSingleNode("descendant::img[$xpathFragmentAddressImg or {$this->eq($this->t("Hotel address"), '@alt')}][1]/following::text()[normalize-space(.)][1]")) {
            $address = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Map & Directions")) . "]/ancestor::table[" . $this->contains($this->t("Plan Your Stay")) . "][1]/preceding-sibling::table[1]/descendant::text()[normalize-space(.)][1]");
        }

        // Phone
        if (!$phone = $this->http->FindSingleNode("//img[$xpathFragmentPhoneImg or {$this->eq($this->t("Telephone number"), '@alt')}]/following::text()[normalize-space(.)][1]")) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Map & Directions"))}]/ancestor::table[{$this->contains($this->t("Plan Your Stay"))}][1]/descendant::text()[normalize-space(.)][1]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        // GuestNames
        $guestName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("For "))}][1]", null, true, "/{$this->opt($this->t("For "))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");
        $h->general()->traveller($guestName);

        // AccountNumbers
        $accountNumbers = $this->http->FindNodes("//text()[" . $this->eq($this->t("REWARDS NUMBER")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]");

        if (!empty($accountNumbers)) {
            $h->program()->accounts($accountNumbers, false);
        }

        $guests = $this->nextText($this->t("GUESTS PER ROOM"));

        // Guests
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t("Adult"))}/", $guests, $m)
            || preg_match("/^(\d{1,3})$/", $guests, $m)
        ) {
            $h->booked()->guests($m[1]);
        }

        // Kids
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t("Child"))}/", $guests, $m)) {
            $h->booked()->kids($m[1]);
        }

        // Rooms
        $roomsCount = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("NUMBER OF ROOMS"))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, '/^\d+$/');
        $h->booked()->rooms($roomsCount);

        $room = $h->addRoom();

        // RoomType
        $room->setType($this->nextText($this->t("ROOM TYPE")));

        $xpathFragmentCharges = "//text()[{$this->eq($this->t("Summary of Charges"))}]";

        // Total
        // Currency
        $totalPayment = $this->http->FindSingleNode($xpathFragmentCharges . "/following::text()[{$this->eq($this->t("Total for stay (for all rooms)"))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $totalPayment, $m)) {
            // 1948.80 MYR
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['currency']));

            // Taxes
            $taxes = $this->http->FindSingleNode($xpathFragmentCharges . "/following::text()[{$this->eq($this->t("ESTIMATED GOVERNMENT TAXES & FEES"))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]");

            if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*' . preg_quote($m['currency'], '/') . '/', $taxes, $matches)) {
                $h->price()->tax($this->amount($matches['amount']));
            }
        }

        // it-3139180.eml, it-4005674.eml
        $nodesToStip = $this->http->XPath->query("//text()[{$this->contains($this->t("Rate and Cancellation Details"))} and ancestor::a] | //*[contains(@style,'overflow:hidden')]");

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        // CancellationPolicy
        $cancellationTexts = $this->http->FindNodes("//text()[{$this->eq($this->t("Rate and Cancellation Details"))} and not(ancestor::a)]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::text()[ normalize-space() and following::text()[{$this->eq($this->t("RATE GUARANTEE LIMITATION(S)"))}] ]");

        if (!empty($cancellationTexts)) {
            $cancellationTexts = array_filter($cancellationTexts, function ($item) {
                return !preg_match('/^\s*[•]+\s*$/', $item);
            });
            $h->general()->cancellation(preg_replace('/\.{2,} /', '. ', implode('. ', $cancellationTexts)));
        }

        // deadline
        $cancellation = $h->getCancellation();

        if ($cancellation && $h->getCheckInDate()) {
            // Thursday, May 22, 2014    |    Mittwoch, 18. Mai 2016    |    Sábado, 30 de Julho de 2016
            $patterns['date'] = '(?:[-[:alpha:]]+, [[:alpha:]]{3,} \d{1,2}, \d{4}|[-[:alpha:]]+, \d{1,2}[.\s]+(?:de )?[[:alpha:]]{3,} (?:de )?\d{4})';

            if (preg_match("/You(?i) may cancell? your reservation for no charge until (?<date>{$patterns['date']})\s*[.(]/u", $cancellation, $m) // en
                || preg_match("/You(?i) may cancell? your reservation for no charge until (?<time>{$patterns['time']}) hotel time on (?<date>{$patterns['date']})\s*[.(]/u", $cancellation, $m) // en
                || preg_match("/Sie(?i) können Ihre Reservierung bis (?<time>{$patterns['time']})(?: Uhr)? Hotelzeit am (?<date>{$patterns['date']}) kostenfrei stornieren\s*[.(]/u", $cancellation, $m) // de
                || preg_match("/Você(?i) pode cancelar sua reserva sem custos até (?<date>{$patterns['date']})\s*[.(]/u", $cancellation, $m) // pt
            ) {
                $dateTime = $this->normalizeDate($m['date']) . (empty($m['time']) ? '' : ', ' . $this->normalizeTime($m['time']));
                $h->booked()->deadline2($dateTime);
            }
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            // Friday, November 3, 2017
            "/^[-[:alpha:]]+[,\s]+([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u",
            // viernes 29 de diciembre de 2017    |    Donnerstag, 19. Mai 2016    |    vendredi 7 juillet 2017
            "/^[-[:alpha:]]+[,\s]+(\d{1,2})[.\s]*(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})$/iu",
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/^(\d{1,2})\s*h\s*(\d{1,2})$/i', '$1:$2', $s); // 15 h 00    ->    15:00
        $s = preg_replace('/^(.{3,}?)\s*(?:hs|Uhr)$/i', '$1', $s); // 11:00 Uhr    ->    11:00

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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]/ancestor::td[1]", $root);
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
