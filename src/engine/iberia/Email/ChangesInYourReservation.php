<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ChangesInYourReservation extends \TAccountChecker
{
    public $mailFiles = "iberia/it-2898696.eml, iberia/it-2913817.eml, iberia/it-4665796.eml, iberia/it-532044201.eml, iberia/it-540551137.eml, iberia/it-57335932.eml, iberia/it-5865872.eml, iberia/it-6888835.eml, iberia/it-6933285.eml, iberia/it-7769011.eml, iberia/it-7787773.eml, iberia/it-7937612.eml, iberia/it-8759399.eml, iberia/it-8832058.eml, iberia/it-8834517.eml, iberia/it-8858404.eml, iberia/it-9027910.eml, iberia/it-9107104.eml, iberia/it-9108474.eml, iberia/it-9132295.eml, iberia/it-9258918.eml, iberia/it-9263784.eml, iberia/it-9263864.eml, iberia/it-9454501.eml, iberia/it-9668626.eml, iberia/it-9729377.eml";
    public $reFrom = "noreply@iberia.es";
    public $reSubject = [
        "en"   => "Changes in your reservation",
        "en2"  => "BOOKING UPDATE",
        "es"   => "Cambios en tu reserva",
        "es2"  => "ACTUALIZACIÓN DE SU RESERVA",
        "pt"   => "Alterações em sua reserva",
        "de"   => "Änderungen Ihrer Buchung",
        "de2"  => "AKTUALISIERUNG IHRER BUCHUNG",
        "fr"   => "Changements à votre réservation",
        "fr2"  => "ACTUALISATION DE VOTRE RÉSERVATION",
        "ru"   => "Изменения бронирования",
        "it"   => "Modifiche alla prenotazione",
    ];
    public $reBody = 'Iberia';
    public $reBody2 = [
        "en"   => "Changes in your booking",
        "en2"  => "Your seat has changed",
        "en3"  => "we have assigned you a place in Business class",
        "es"   => "Cambios en su reserva",
        "es2"  => "La modificación es la siguiente",
        "pt"   => "Alterações na sua reserva",
        "de"   => "Änderungen in Ihrer Buchung",
        "de2"  => "Ihr Sitzplatz hat sich geändert",
        "fr"   => "changements ont été apportés à votre vol",
        "fr2"  => "Votre siège a changé.",
        "ru"   => "рейсе произошли некоторые изменения",
        "it"   => "Cambio di volo",
    ];
    public $date;

    public static $dictionary = [
        "en" => [
            "Booking Number:"   => ["Booking Number:", "Reservation code:"],
            "Dear "             => ["Dear Sir/Madam ", "Dear "],
            "Your new flight"   => ["Your new flight", "Your current flight"],
            "Your current seat" => ["Your current seat", "Current seat"],
            "Seat:"             => "Seat:",
            "Passenger"         => ["Passenger", "Passengers"],
            "Terminal"          => 'Terminal',
        ],
        "es" => [
            "Booking Number:"   => ["Código de reserva:", "Número de reserva:"],
            "Dear "             => ["Estimado Señor/señora ", "Estimado señor/señora "],
            "Your new flight"   => ["Su nuevo vuelo", "Su vuelo actual", "Nuevo vuelo", "Vuelo actual"],
            "Your current seat" => ["Asiento actual", 'Asiento Actual'],
            "Flights cancelled" => ["Vuelos cancelados", "Vuelo cancelado"],
            "Passenger"         => ["Pasajero", "Pasajeros"],
            "Terminal"          => 'Terminal',
            "Seat:"             => "Asiento:",
        ],
        "pt" => [
            "Booking Number:" => "Código de reserva:",
            "Dear "           => "Estimado ",
            "Your new flight" => ["O seu novo voo", "O seu voo atual"],
            //			"Your current seat" => [],
            //			"Seat:" => "",
            "Flights cancelled" => "Voos cancelados",
            "Passenger"         => ["Passageiro", "Passageiros"],
            "Terminal"          => 'Terminal',
        ],
        "de" => [
            "Booking Number:"   => ["Reservierungscode:", "Buchungsnummer:", 'Código de reserva:'],
            "Dear "             => ["Sehr geehrter Herr / Sehr geehrte Frau ", "Sehr geehrter Herr / sehr geehrte Frau "],
            "Your new flight"   => ["Ihr aktueller Flug", "Ihr neuer Flug"],
            "Your current seat" => ["Aktueller Sitzplatz"],
            "Seat:"             => "Sitzplatz:",
            "Flights cancelled" => "Annullierte Flüge",
            "Passenger"         => ["Passagier", "Passagiere"],
            "Terminal"          => 'Terminal',
        ],
        "fr" => [
            "Booking Number:"   => ["Code de réservation:", "Numéro de réservation:"],
            "Dear "             => "Très chers Monsieur/Madame ",
            "Your new flight"   => ["Votre vol actuel", "Votre nouveau vol"],
            "Your current seat" => ['Siège actuel'],
            "Seat:"             => "Siège:",
            "Flights cancelled" => "NOTTRANSLATED",
            "Passenger"         => "Passager",
            "Terminal"          => 'Terminal',
        ],
        "ru" => [
            "Booking Number:" => "Код бронирования:",
            "Dear "           => "Уважаемый господин/госпожа ",
            "Your new flight" => ["Ваш новый рейс"],
            //			"Your current seat" => [],
            //			"Seat:" => "",
            "Flights cancelled" => "NOTTRANSLATED",
            "Passenger"         => "Nассажирские", // it's not mistake
            "Terminal"          => 'Терминал',
        ],
        "it" => [
            "Booking Number:" => "Codice della prenotazione:",
            "Dear "           => "Gent.mo/a signor/signora ",
            "Your new flight" => ["Il Suo volo attuale"],
            //			"Your current seat" => [],
            //			"Seat:" => "",
            "Flights cancelled" => "NOTTRANSLATED",
            "Passenger"         => "Passeggero",
            "Terminal"          => 'Terminal',
        ],
    ];

    public $lang = "en";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->date = strtotime("- 7 day", $this->date);

        $this->http->FilterHTML = true;
        $this->http->setBody(html_entity_decode($parser->getHTMLBody()));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseHtml(Email $email)
    {
        $flight = $email->add()->flight();

        // General
        $confText = $this->nextText($this->t("Booking Number:"));

        if (preg_match("/^([A-Z\d]{6})\/([A-Z\d]{5,6})$/", $confText, $m)
            || preg_match("/^([A-Z\d]{5,6})$/", $confText, $m)) {
            $flight->general()
                ->confirmation($m[1]);

            if (!empty($m[2])) {
                $flight->general()
                    ->confirmation($m[2]);
            }
        }

        $passengersNodes = $this->http->XPath->query("(.//text()[" . $this->eq($this->t("Passenger")) . "])[1]/ancestor::td[1]/following-sibling::td[1]//tr[not(.//tr)]");

        foreach ($passengersNodes as $pRoot) {
            $flight->general()
                ->traveller(implode(' ', preg_replace("/\s+(MS|MR|MRS|MISS|MSTR|DR)$/", '',
                    $this->http->FindNodes("*", $pRoot))), true);
        }

        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your new flight")) . "]")) {
            $flight->general()
                ->status('changed');
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("(.//text()[" . $this->eq($this->t("Passenger")) . "])[1]/ancestor::td[1]/following-sibling::td[position() > 1]//tr[not(.//tr)]",
            null, "/^\s*(\d{13})\s*$/"));

        if (!empty($tickets)) {
            $flight->issued()
                ->tickets($tickets, false);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Your new flight")) . "]/following::table[.//tr/td[2]][1]/descendant::tr/td[2]//tr[./td[2]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->eq($this->t("Flights cancelled")) . "]/following::table[1]//tr[./td[2]]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $segment = $flight->addSegment();

            if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Flights cancelled")) . "]")) {
                $segment->extra()->cancelled();
            }

            // Airline
            $segment->airline()
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#\w{2}(\d+)#"))
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#(\w{2})\d+#"));

            // DepCode
            if (empty($depCode = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#(?:^|\()([A-Z]{3})(?:$|\))#"))) {
                $segment->departure()
                    ->noCode();
            } else {
                $segment->departure()
                    ->code($depCode);
            }

            // DepName
            if (empty($depCode)) {
                $depName = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root);

                if (empty($depName)) {
                    $depName = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=4]", $root);
                }
                $segment->departure()
                    ->name($depName);
            }

            // DepartureTerminal
            // DepDate
            $depTerminal = $this->http->FindSingleNode("./td[1]/descendant::text()[" . $this->contains($this->t("Terminal")) . "][1]",
                $root, true, "/" . $this->opt($this->t("Terminal")) . "\s+([\w ]+)$/");

            if (!empty($depTerminal)) {
                $segment->departure()
                    ->terminal($depTerminal);
            }

            $segment->departure()
                ->date($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][position()<3]", $root))));

            // ArrCode
            // ArrName
            if (empty($arrCode = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#^([A-Z]{3})$#"))) {
                $segment->arrival()
                    ->noCode();
            } else {
                $segment->arrival()
                    ->code($arrCode);
            }

            if (empty($segment->getArrCode())) {
                $arrName = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][position()=3]", $root);

                if (empty($arrName)) {
                    $arrName = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][position()=4]", $root);
                }
                $segment->arrival()
                    ->name($arrName);
            }

            // ArrivalTerminal
            // ArrDate
            $arrTerminal = $this->http->FindSingleNode("./td[2]/descendant::text()[" . $this->contains($this->t("Terminal")) . "][1]",
                $root, true, "/" . $this->opt($this->t("Terminal")) . "\s+([\w ]+)$/");

            if (!empty($arrTerminal)) {
                $segment->arrival()
                    ->terminal($arrTerminal);
            }

            $segment->arrival()
                ->date($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()<3]", $root))));
        }
        // SEAT

        $xpath = "//text()[" . $this->eq($this->t("Your current seat")) . "]/following::table[.//tr/td[2]][1]/descendant::tr/td[2]//tr[not(./td[2])]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->count() > 0) {
            $allSegment = $this->http->FindNodes("//text()[" . $this->eq($this->t("Your current seat")) . "]/following::table[.//tr/td[2]][1]/descendant::tr/td[2]//tr[not(./td[2])]/following-sibling::tr[1]/ancestor::table[normalize-space()][1]", null, '/(\D+\d+\s+\d+\s+\w+[\d\:]+)\s+[A-Z\s]+/');
            $uniqueSegment = array_unique($allSegment);

            foreach ($nodes as $root) {
                $segment = $flight->addSegment();

                if (count($uniqueSegment) < count($allSegment) & count($uniqueSegment) === 1) {
                    $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Your current seat")) . "]/following::table[.//tr/td[2]][1]/descendant::tr/td[2]//tr[not(./td[2])]/following-sibling::tr[1]/descendant::td[normalize-space()][last()]");
                    $flight->general()
                        ->travellers(array_filter($travellers), true);
                } else {
                    $flight->general()->traveller($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][position()=1]", $root, true, "#^([A-Z\- \.]+)$#"));
                }

                // FlightNumber
                $segment->airline()
                    ->number($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#\w{2}(\d+)#"));

                // DepCode
                $depCode = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#^([A-Z]{3})\s*-\s*[A-Z]{3}$#");

                if (empty($depCode)) {
                    $depCode = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][position()=1]", $root, true, "#^([A-Z]{3})\s*-\s*[A-Z]{3}$#");
                }

                if (!empty($depCode)) {
                    $segment->departure()
                        ->code($depCode);
                }

                // DepName
                if (empty($depCode)) {
                    $name = trim($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#^(.+)\s*-\s*.+$#"));

                    if (!empty($name)) {
                        $segment->departure()
                            ->name($name)
                            ->noCode();
                    }
                }

                // DepartureTerminal
                // DepDate
                $segment->departure()
                    ->date($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[1]/descendant::text()[normalize-space(.)][position()<3]", $root))));

                // ArrCode
                $arrCode = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#^[A-Z]{3}\s*-\s*([A-Z]{3})$#");

                if (empty($arrCode)) {
                    $arrCode = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)][position()=1]", $root, true, "#^\s*[A-Z]{3}\s*-\s*([A-Z]{3})\s*$#");
                }

                if (!empty($arrCode)) {
                    $segment->arrival()
                        ->code($arrCode);
                }

                // ArrName
                if (empty($arrCode)) {
                    $name = trim($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][position()=3]", $root, true, "#^.+\s*-\s*(.+)$#"));

                    if (!empty($name)) {
                        $segment->arrival()
                            ->name($name)
                            ->noCode();
                    }
                }

                // ArrivalTerminal
                // ArrDate
                $segment->arrival()->noDate();

                // AirlineName
                $segment->airline()
                    ->name($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#(\w{2})\d+#"));

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (count($uniqueSegment) < count($allSegment) & count($uniqueSegment) === 1) {
                    $segment->extra()
                        ->seats($this->http->FindNodes("//text()[" . $this->eq($this->t("Your current seat")) . "]/following::table[.//tr/td[2]][1]/descendant::tr/td[2]//tr[not(./td[2])]/following-sibling::tr[1]/following::tr[1]/descendant::td[starts-with(normalize-space(), 'Seat')]", null, '/Seat[:]\s+([A-Z\d]+)/'));
                } else {
                    $seat = $this->nextText($this->t("Seat:"), $root);

                    if (empty($seat)) {
                        $seat = $this->http->FindSingleNode("(./following-sibling::tr[1]//text()[" . $this->eq($this->t("Seat:")) . "])[1]/following::text()[normalize-space(.)][1]", $root);
                    }

                    if (!empty($seat)) {
                        $segment->extra()
                            ->seat($seat);
                    }
                }

                if (count($uniqueSegment) < count($allSegment) & count($uniqueSegment) === 1) {
                    break;
                }

                $segments = $flight->getSegments();

                foreach ($segments as $seg) {
                    if ($segment->getId() !== $seg->getId()) {
                        if ($segment->getAirlineName() == $seg->getAirlineName()
                            && $segment->getFlightNumber() == $seg->getFlightNumber()
                            && $segment->getDepDate() == $seg->getDepDate()
                        ) {
                            if (!empty($segment->getSeats())) {
                                $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $segment->getSeats())));
                            }
                            $flight->removeSegment($segment);
                        }
                    }
                }
            }
        }

        if (empty($flight->getTravellers())) {
            $passengers = array_filter([trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true, "#(?:" . $this->preg_implode($this->t("Dear ")) . ")(.*?)[,:]#"))]);

            if (isset($passengers)) {
                $flight->general()->travellers(array_unique(array_filter($passengers)));
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
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
        $body = html_entity_decode($parser->getHTMLBody());

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));

        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+),\s+(\d+:\d+)$#", //29 June, 06:55
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $date = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'pt')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!preg_match("/\b\d{4}\b/", $str) && !empty($this->date)) {
            $date = EmailDateHelper::parseDateRelative($date, $this->date);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }
}
