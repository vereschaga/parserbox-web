<?php

namespace AwardWallet\Engine\lastminute\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationFlightReservation extends \TAccountChecker
{
    public $mailFiles = "lastminute/it-12638415.eml, lastminute/it-12665854.eml, lastminute/it-12912478.eml, lastminute/it-13028467.eml, lastminute/it-13289237.eml, lastminute/it-13410997.eml, lastminute/it-1639705.eml, lastminute/it-1740916.eml, lastminute/it-1920617.eml, lastminute/it-2521121.eml, lastminute/it-2604119.eml, lastminute/it-2956682.eml, lastminute/it-2956770.eml, lastminute/it-3131637.eml, lastminute/it-4862570.eml, lastminute/it-4863289.eml, lastminute/it-4888132.eml, lastminute/it-4892578.eml, lastminute/it-4908584.eml, lastminute/it-4934818.eml, lastminute/it-4939664.eml, lastminute/it-6051788.eml";

    public static $froms = [
        'bravofly'   => ['bravofly.'],
        'rumbo'      => ['rumbo.'],
        'volagratis' => ['volagratis.'],
        'lastminute' => ["@lastminute.com"],
        ''           => [".customer-travel-care.com"],
    ];
    private $reSubject = [
        'en' => 'Confirmation of your flight reservation',
        'de' => 'Bestätigung der Flugreservierung',
        'fr' => 'Confirmation de réservation du voyage',
        'it' => 'Conferma prenotazione viaggio',
        'ru' => 'Подтверждение Вашего бронирования',
        'da' => 'Bekræftelse af din flyreservation',
        'fi' => 'Lentosi varausvahvistus',
        'no' => 'Bestillingsbekreftelse',
        'es' => 'Confirmación reserva vuelo',
        'pt' => 'Confirmação da sua reserva de voo',
        'hu' => 'Foglalás visszaigazolása',
    ];

    private $logo = [
        'bravofly'   => ['bravofly', 'logo-BF', 'BRAVOFLY'],
        'rumbo'      => ['rumbo', 'RUMBO'],
        'volagratis' => ['logo-VG', 'volagratis', 'VOLAGRATIS'],
        'lastminute' => ['lastminute', 'LASTMINUTE'],
    ];

    private $reBody = [
        'bravofly'   => ['bravofly', 'bravoavia'],
        'rumbo'      => ['rumbo'],
        'volagratis' => ['volagratis'],
        'lastminute' => ['lastminute.com'],
    ];
    private $reBody2 = [
        "en" => "Departure",
        "de" => "Abflug",
        "fr" => "Départ",
        "it" => "Partenza",
        "ru" => "Вылет",
        "da" => "Afgang",
        "fi" => "Lähtö",
        "no" => "Avgang",
        "es" => "Origen",
        "pt" => "Partida",
        "hu" => "Indulás",
    ];

    private static $dictionary = [
        "en" => [
            //			"Reservations and assistance" => "",
            //			"Dear" => "",
            //			"ID Booking" => "",
            //			"TRAVELERS" => "",
            "Adult:" => ["Adult:", "Child:", "Infant:"],
            //			"Electronic ticket number" => "",
            //			"Return Flight" => "",
            //			"Reservation Number (PNR)" => "",
            //			"Departure" => "",
            //			"Operated by" => "",
        ],
        "de" => [
            "Reservations and assistance" => "Kundendienst",
            "Dear"                        => "Sehr geehrte(r)",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "PASSAGIERE",
            "Adult:"                      => ["Erwachsener:", "Kind:"],
            "Electronic ticket number"    => "Buchungscode des elektronischen Tickets",
            "Return Flight"               => "Rückflug",
            "Reservation Number (PNR)"    => "Buchungscode (PNR)",
            "Departure"                   => "Abflug",
            "Operated by"                 => "Durchgeführt von",
        ],
        "fr" => [
            "Reservations and assistance" => "Réservations",
            "Dear"                        => ["Cher/Chère", "Bonjour"],
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "INFORMATIONS PASSAGERS",
            "Adult:"                      => ["Adulte:", "Enfant:", "Bébé:"],
            "Electronic ticket number"    => "Numéro du billet électronique",
            "Return Flight"               => "Voyage de retour",
            "Reservation Number (PNR)"    => ["Codes de réservation (PNR)", "Code de réservation (PNR)"],
            "Departure"                   => "Départ",
            "Operated by"                 => "Effectué par",
        ],
        "it" => [
            "Reservations and assistance" => "Per prenotazioni",
            "Dear"                        => "Gentile",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "VIAGGIATORI",
            "Adult:"                      => "Adulto:",
            "Electronic ticket number"    => "Numero di biglietto elettronico",
            //			"Return Flight" => "",
            "Reservation Number (PNR)" => "Codice prenotazione (PNR)",
            "Departure"                => "Partenza",
            "Operated by"              => "Operato da",
        ],
        "ru" => [
            "Reservations and assistance" => "Бронирование и поддержка",
            "Dear"                        => "Уважаемый(ая)",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "ПАССАЖИРЫ",
            "Adult:"                      => ["Взрослый:", "Ребенок:"],
            "Electronic ticket number"    => "Номер электронного билета",
            "Return Flight"               => "Перелет обратно",
            "Reservation Number (PNR)"    => "Код брони (PNR)",
            "Departure"                   => "Вылет",
            "Operated by"                 => "Управляемый",
        ],
        "da" => [
            "Reservations and assistance" => "Reservationer og assistance",
            "Dear"                        => "Kære",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "REJSENDE",
            "Adult:"                      => "Adult:",
            "Electronic ticket number"    => "Elektronisk billetnummer",
            //			"Return Flight" => "",
            "Reservation Number (PNR)" => "Reservationsnummer (PNR)",
            "Departure"                => "Afgang",
            "Operated by"              => "Operated by",
        ],
        "fi" => [
            "Reservations and assistance" => "Varaukset ja tuki",
            "Dear"                        => "Hyvä",
            "ID Booking"                  => "ID-Booking",
            "TRAVELERS"                   => "MATKUSTAJAT",
            "Adult:"                      => "Aikuinen:",
            "Electronic ticket number"    => "Sähköisen matkalipun numero",
            //			"Return Flight" => "",
            "Reservation Number (PNR)" => "Varauskoodit (PNR)",
            "Departure"                => "Lähtö",
            "Operated by"              => "Operoiva yhtiö",
        ],
        "no" => [
            "Reservations and assistance" => "Bestilling og Assistanse",
            "Dear"                        => "Kjære",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "PASSASJERER",
            "Adult:"                      => "Voksen:",
            "Electronic ticket number"    => "Antall elektroniske billette",
            //			"Return Flight" => "",
            "Reservation Number (PNR)" => "eservasjonsnummer (PNR)",
            "Departure"                => "Avgang",
            "Operated by"              => "Operert av",
        ],
        "es" => [
            "Reservations and assistance" => "Reservas y asistencia",
            "Dear"                        => "Estimado/a",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "PASAJEROS",
            "Adult:"                      => ["Adulto:", "Niño:"],
            "Electronic ticket number"    => "Número de billete electrónico",
            "Reservation Number (PNR)"    => "Localizador (PNR)",
            "Return Flight"               => "Vuelo de vuelta",
            "Departure"                   => "Origen",
            "Operated by"                 => "Operado por",
        ],
        "pt" => [
            "Reservations and assistance" => "Reservas e assistência",
            "Dear"                        => "Estimado/a",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "PASSAGEIROS",
            "Adult:"                      => "Adulto:",
            "Electronic ticket number"    => "Número do bilhete electrónico",
            "Return Flight"               => "Voo de volta",
            "Reservation Number (PNR)"    => "Código de Reserva (PNR)",
            "Departure"                   => "Partida",
            "Operated by"                 => "Operado por",
        ],
        "hu" => [
            "Reservations and assistance" => "Ügyfélszolgálat",
            "Dear"                        => "Kedves",
            "ID Booking"                  => "ID Booking",
            "TRAVELERS"                   => "UTASOK",
            "Adult:"                      => "Felnőtt:",
            "Electronic ticket number"    => "Elektronikus jegy száma",
            //			"Return Flight" => "",
            "Reservation Number (PNR)" => "Foglalási szám (PNR)",
            "Departure"                => "Indulás",
            //			"Operated by" => "",
        ],
    ];

    private $lang = "en";
    private $codeProvider = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetBody(html_entity_decode($parser->getHTMLBody()));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        } else {
            $email->ota()->code('lastminute');
        }

        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("ID Booking")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber);
        }
        $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservations and assistance")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([\d\+\- \(\).]{5,})\s*$#");

        if (!empty($phone)) {
            if (is_array($this->t("Reservations and assistance"))) {
                $email->ota()->phone($phone, $this->t("Reservations and assistance")[0]);
            } else {
                $email->ota()->phone($phone, $this->t("Reservations and assistance"));
            }
        }
        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (strpos($headers["from"], $value) !== false) {
                    $head = true;
                    $this->codeProvider = $prov;

                    break 2;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

        $head = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $from . '")]')->length > 0 || stripos($body, $from) !== false) {
                    $head = true;

                    break;
                }
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$froms));
    }

    private function flight(Email $email)
    {
        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return null;
        }

        $ticketsCompany = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Electronic ticket number")) . "]", null, "#:\s*(\d{3})\d{6,}\s*$#"));

        if (count($ticketsCompany) == 0) {
            $airs['all'] = $nodes;
        } elseif (count($ticketsCompany) == 1 && empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("TRAVELERS")) . "]/ancestor::tr[2][" . $this->starts($this->t("TRAVELERS")) . "]/following-sibling::tr[" . $this->eq($this->t("Return Flight")) . "])[1]"))) {
            $tickets['all'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Electronic ticket number")) . "]", null, "#:\s*(\d{9,})\s*$#")));
            $airs['all'] = $nodes;
        } elseif (count($ticketsCompany) > 0) {
            $tickets['outbound'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("TRAVELERS")) . "]/ancestor::tr[2][" . $this->starts($this->t("TRAVELERS")) . "]/following-sibling::tr[" . $this->eq($this->t("Return Flight")) . "]/preceding-sibling::tr//text()[" . $this->starts($this->t("Electronic ticket number")) . "]", null, "#:\s*(\d{9,})\s*$#")));
            $tickets['return'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("TRAVELERS")) . "]/ancestor::tr[2][" . $this->starts($this->t("TRAVELERS")) . "]/following-sibling::tr[" . $this->eq($this->t("Return Flight")) . "]/following-sibling::tr//text()[" . $this->starts($this->t("Electronic ticket number")) . "]", null, "#:\s*(\d{9,})\s*$#")));
            $tOutbound = array_values(array_unique(array_map(function ($v) {return substr(trim($v), 0, 3); }, $tickets['outbound'])));
            $tReturn = array_values(array_unique(array_map(function ($v) {return substr(trim($v), 0, 3); }, $tickets['return'])));

            if (count($tOutbound) == 1 && count($tReturn) == 1 && $tOutbound[0] == $tReturn[0]) {
                $airs['all'] = $nodes;
                $tickets['all'] = array_merge($tickets['outbound'], $tickets['return']);
            } else {
                foreach ($nodes as $root) {
                    if (!empty($this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)][1]/ancestor::table[1]//text()[" . $this->starts($this->t("Return Flight")) . "]", $root))) {
                        $airs['return'][] = $root;
                    } else {
                        $airs['outbound'][] = $root;
                    }
                }
            }
        }

        foreach ($airs as $key => $roots) {
            $f = $email->add()->flight();

            if (is_array($this->t("Reservation Number (PNR)"))) {
                $rlName = $this->t("Reservation Number (PNR)")[0];
            } else {
                $rlName = $this->t("Reservation Number (PNR)");
            }

            if ($key == 'all') {
                $rls = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Reservation Number (PNR)")) . "]", null, "#\(PNR\)\s*:\s*([A-Z\d]{5,7})\b#"));

                if (!empty($rls)) {
                    foreach ($rls as $rl) {
                        $f->general()->confirmation($rl, $rlName);
                    }
                } elseif (!empty(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Reservation Number (PNR)")) . "]", null, "#\(PNR\)\s*:\s*(SpecialOffers)#")))) {
                    $f->general()->noConfirmation();
                }
            } else {
                $action = '';

                if ($key == 'outbound') {
                    $action = 'not';
                }
                $rls = array_filter($this->http->FindNodes("//text()[(" . $this->contains($this->t("Reservation Number (PNR)")) . ") and {$action}(" . $this->contains($this->t("Return Flight"), 'ancestor::table[1]') . ")]", $root, "#\(PNR\)\s*:\s*([A-Z\d]{5,7})\b#"));

                if (!empty($rls)) {
                    foreach ($rls as $rl) {
                        $f->general()->confirmation($rl, $rlName);
                    }
                }
            }

            if (isset($tickets[$key])) {
                $f->issued()->tickets($tickets[$key], false);
            }
            $passengers = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Adult:")) . "]", null, "#:\s+(.+)#"));

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, true);
            }

            if (empty($passengers)) {
                $passengers = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "]", null, true, "#" . $this->starts($this->t("Dear")) . "\s+(.+)#");
                $f->general()->travellers($passengers, false);
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][last()-4]", $root)));

                $rl = $this->http->FindSingleNode("./preceding::table[1]//text()[" . $this->contains($this->t("Reservation Number (PNR)")) . "]", $root, true, "#\(PNR\)\s*:\s*([A-Z\d]{5,7})\b#");

                if (!empty($rl)) {
                    $s->airline()->confirmation($rl);
                }

                $s->airline()
                    ->name($this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s+\d+$#"))
                    ->number($this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s+(\d+)$#"))
                    ->operator($this->http->FindSingleNode("./td[normalize-space(.)][last()-3]/descendant::text()[normalize-space(.)][2]", $root, true, "#" . $this->t("Operated by") . "\s+(.+)#"), true, true);

                $s->departure()
                    ->noCode()
                    ->name($this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\d+:\d+\s+(.+)#"))
                    ->terminal(trim(str_ireplace('Terminal', '', $this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][2]", $root))), true, true)
                    ->date(strtotime($this->http->FindSingleNode("./td[normalize-space(.)][last()-1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\d+:\d+)\s+#"), $date));

                $s->arrival()
                    ->noCode()
                    ->name($this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\d+:\d+\s+(.+)#"))
                    ->terminal(trim(str_ireplace('Terminal', '', $this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][2]", $root))), true, true)
                    ->date(strtotime($this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\d+:\d+)\s+#"), $date));

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }

                $s->extra()->cabin($this->http->FindSingleNode("./td[normalize-space(.)][last()-2]/descendant::text()[normalize-space(.)][2]", $root));
            }
        }

        return $email;
    }

    private function getProvider()
    {
        foreach ($this->logo as $prov => $paths) {
            foreach ($paths as $path) {
                if ($this->http->XPath->query('//img[contains(@src, "' . $path . '") and contains(@src, "logo")]')->length > 0) {
                    return $prov;
                }
            }
        }
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return $prov;
                }
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\s+(\d+)/(\d+)/(\d{4})$#", //Tue 02/09/2014
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "normalize-space({$text})=\"{$s}\""; }, $field));
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "starts-with(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
