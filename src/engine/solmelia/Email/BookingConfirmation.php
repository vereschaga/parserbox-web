<?php

namespace AwardWallet\Engine\solmelia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "solmelia/it-115715313.eml, solmelia/it-117169308.eml, solmelia/it-157518601.eml, solmelia/it-1615756.eml, solmelia/it-1624253.eml, solmelia/it-1639503.eml, solmelia/it-1642743.eml, solmelia/it-1670986.eml, solmelia/it-1828126.eml, solmelia/it-2596678.eml, solmelia/it-2931493.eml, solmelia/it-33833210.eml, solmelia/it-33853031.eml, solmelia/it-3945002.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            "your booking is confirmed" => "",
            //            "booking has been CANCELLED" => "",
            //            "Booking reference code" => "",
            //            "Personal details:" => "",
            //            "Hotel booked:" => "",
            //            "Tel" => "",
            //            "Fax" => "",
            //            "Arrival date:" => "",
            //            "Departure date:" => "",
            //            "Number of rooms:" => "",
            "Category:" => ["Category:", "Room type:"],
            "Room "     => ["Room "],
            //            "Cancelada"     => "", // to translate
            //            "Occupancy:"     => "",
            //            "adult" => "",
            "children" => ["children", "child"],
            //            "MeliáRewards card:" => "",
            "Total cost including taxes" => ["Total cost including taxes", "Total cost:"],
            //            "Total cost not including taxes" => "",
            //            "Cancellation Policy:" => "", //for cancelled email
            //            "points" => "",
            "Cancellation of the reservation" => ["Cancellation of the reservation", "Booking changes or cancellation"],
        ],
        "pt" => [
            "your booking is confirmed" => "sua reserva está confirmada",
            //            "booking has been CANCELLED" => "",
            "Booking reference code" => "Localizador de reserva",
            "Personal details:"      => "Titular da reserva:",
            "Hotel booked:"          => "Hotel reservado:",
            "Tel"                    => "Tel",
            "Fax"                    => "Fax",
            "Arrival date:"          => "Data de entrada:",
            "Departure date:"        => "Data de saída:",
            "Number of rooms:"       => "Nº de quartos:",
            "Category:"              => "Categoria:",
            "Room "                  => "Apartamento ",
            //            "Cancelada"     => "",
            "Occupancy:"                 => "Ocupação:",
            "adult"                      => "adulto",
            "children"                   => "criança",
            "MeliáRewards card:"         => "Cartão MeliáRewards:",
            "Total cost including taxes" => ["Custo total impostos incluídos ("],
            //            "Total cost not including taxes" => "",
            "Cancellation Policy:"       => "Política de cancelamento:", //for cancelled email
            //            "points" => "",
            "Cancellation of the reservation" => ["Cancelamento da reserva"],
        ],
        "de" => [
            "your booking is confirmed"  => "Ihre Buchung ist jetzt bestätigt",
            "booking has been CANCELLED" => "Buchung wurde ERFOLGREICH STORNIERT",
            "Booking reference code"     => "Reservierungs-nummer",
            "Personal details:"          => "Persönliche Daten:",
            "Hotel booked:"              => "Gebuchtes Hotel:",
            "Tel"                        => "Tel",
            "Fax"                        => "Fax",
            "Arrival date:"              => "Ankunftsdatum:",
            "Departure date:"            => "Abreisedatum:",
            "Number of rooms:"           => "Anzahl der Zimmer:",
            "Category:"                  => ["Kategorie:", "Zimmerart:"],
            "Room "                      => "Zimmer ",
            //            "Occupancy:"     => "",
            //            "Cancelada"     => "",
            "adult"                      => "Erwachsene",
            //            "children" => "",
            "MeliáRewards card:"              => "MeliáRewards Karte:",
            "Total cost including taxes"      => ["Gesamtkosten inkl. anfallender Steuern", "Gesamtkosten:"],
            //            "Total cost not including taxes" => "",
            "Cancellation Policy:"            => "Garantie erforderlich:", // for cancelled email
            "points"                          => "Punkte",
            "Cancellation of the reservation" => ["Änderung oder Stornierung dieser Buchung", "Stornierung der Reservierung"],
        ],
        "es" => [
            "your booking is confirmed"       => "tu reserva está confirmada",
            "booking has been CANCELLED"      => ["La reserva que se detalla a continuación ha sido CANCELADA CON ÉXITO", "Detalle de la reserva cancelada"],
            "Booking reference code"          => ["Número de localizador", "Confirmación de la reserva - LOC"],
            "Personal details:"               => "Titular de la reserva:",
            "Hotel booked:"                   => "Hotel reservado:",
            "Tel"                             => "Tel",
            "Fax"                             => "Fax",
            "Arrival date:"                   => "Fecha de entrada:",
            "Departure date:"                 => "Fecha de salida:",
            "Number of rooms:"                => ["Número de habitaciones:", "Tipo de habitación:"],
            "Category:"                       => ["Categoría:", "Tipo de habitación:"],
            "Room "                           => "Habitación ", //Tipo de habitación:
            "Occupancy:"                      => "Ocupación:",
            "Cancelada"                       => "Cancelada",
            "adult"                           => ["adulto", "adultos"],
            "children"                        => "niño",
            "MeliáRewards card:"              => "Tarjeta MeliáRewards:",
            "Total cost including taxes"      => ["Coste total impuestos incluidos", "Coste total:"],
            "Total cost not including taxes"  => "Coste total impuestos no incluidos",
            "Cancellation Policy:"            => "Política de cancelación:", //for cancelled email
            "points"                          => ["Puntos", "puntos"],
            "Cancellation of the reservation" => ["Modificación o cancelación de la reserva", "Cancelación de la reserva"],
        ],
        "fr" => [
            "your booking is confirmed" => "votre réservation a bien été réalisée",
            //            "booking has been CANCELLED" => "",
            "Booking reference code" => "Localisateur de la réservation",
            "Personal details:"      => "Personne qui effectue la réservation:",
            "Hotel booked:"          => "Hôtel réservé:",
            "Tel"                    => "Tel",
            "Fax"                    => "Fax",
            "Arrival date:"          => "Date d'entrée:",
            "Departure date:"        => "Date de départ:",
            "Number of rooms:"       => "Nombre de chambres:",
            "Category:"              => "Catégorie:",
            "Room "                  => "Chambre ",
            //            "Cancelada"     => "",
            //            "Occupancy:"     => "",
            "adult"                  => "adult",
            //            "children" => "",
            //            "MeliáRewards card:" => "",
            "Total cost including taxes" => "Coût total taxes comprises",
            //            "Total cost not including taxes" => "",
            //            "Cancellation Policy:" => "", //for cancelled email
            //            "points" => "",
            "Cancellation of the reservation" => ["Modification ou annulation de la réservation", "Annulation de la réservation"],
        ],
        "it" => [
            "your booking is confirmed" => "la tua prenotazione è confermata",
            //            "booking has been CANCELLED" => "",
            "Booking reference code" => "Localizzatore della prenotazione",
            "Personal details:"      => "Titolare della prenotazione:",
            "Hotel booked:"          => "Hotel prenotato:",
            "Tel"                    => "Tel",
            "Fax"                    => "Fax",
            "Arrival date:"          => "Data di arrivo:",
            "Departure date:"        => "Data di partenza:",
            "Number of rooms:"       => "Nº delle camere:",
            "Category:"              => "Categoria:",
            "Room "                  => "Camera ", // to check
            //            "Cancelada"     => "",
            "Occupancy:"     => "Occupazione:",
            "adult"                  => "adult",
            //            "children" => "",
            "MeliáRewards card:"         => "Tessera MeliáRewards:",
            "Total cost including taxes" => "Costo totale tasse incluse",
            //            "Total cost not including taxes" => "",
            //            "Cancellation Policy:" => "", //for cancelled email
            //            "points" => "",
            "Cancellation of the reservation" => "Cancellazione della prenotazione",
        ],
    ];

    private $detectFrom = "melia.com";
    private $detectSubject = [
        "en" => "Booking confirmation - LOC:",
        "Cancellation of the reservation",
        "pt" => "Confirmação de reserva - LOC:",
        "de" => "Reservierungsbestätigung - LOC:",
        "Stornierung der Reservierung",
        "es" => "Confirmación de la reserva - LOC:", "Cancelación de la reserva",
        "fr" => "Confirmation de la réservation - LOC:",
        "it" => "Dettagli della tua prenotazione - LOC:",
    ];

    private $detectCompany = 'melia.com';
    private $detectBody = [
        "en"  => "Booking reference code",
        "pt"  => "Localizador de reserva",
        "de"  => "Reservierungs-nummer",
        "es"  => "Número de localizador",
        "es2" => "Detalle de tu reserva",
        "fr"  => "Localisateur de la réservation",
        "it"  => "Localizzatore della prenotazione",
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body) && stripos($parser->getPlainBody(), '<html') !== false) {
            $this->http->SetEmailBody($parser->getPlainBody());
            $body = $this->http->Response['body'];
        }

        if ($this->http->XPath->query("//a[contains(@href,'{$this->detectCompany}')] | //*[contains(.,'{$this->detectCompany}')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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

    private function parseHotel(Email $email): void
    {
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference code")) . "]", null, true, "#:\s+(.+)#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference code")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d{5,})\s*$#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Booking reference code")) . "]", null, true, "#LOC:(\d{5,})\b#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel booked:'))}]/preceding::text()[{$this->starts($this->t('Booking reference code'))}][1]", null, true, "#:\s+(.+)#");
        }

        $isCancelled = false;

        if ($this->http->XPath->query("descendant::text()[{$this->contains($this->t("your booking is confirmed"))}]")->length > 0) {
            $status = 'Confirmed';
        } elseif ($this->http->XPath->query("descendant::text()[{$this->contains($this->t("booking has been CANCELLED"))}]")->length > 0) {
            $status = 'Cancelled';
            $isCancelled = true;
        }

        // Hotel
        $hotelName = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotel booked:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]"), ' -');
        $hotelText = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel booked:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][not(ancestor::a)][position()>1]"));

        if (preg_match("#^\s*(.+?)[\s,-]+(?:Tel|Fax|$)#", $hotelText, $m)) {
            $hotelAddress = $m[1];
        }

        // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    1/809/6105800
        $patterns['phone'] = '[+(\d][-+. \/\d)(]{5,}[\d)]';

        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Tel"))}]", null, true, "#{$this->preg_implode($this->t("Tel"))}[.]?\s*({$patterns['phone']})([ -]*{$this->preg_implode($this->t("Fax"))}|$)#");

        if (empty($phone) && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Tel"))}]", null, true, "#{$this->preg_implode($this->t("Tel"))}[.]?[-\s]*$#"))) {
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Tel"))}]/following::text()[normalize-space()][1]", null, true, "#^\s*({$patterns['phone']})([ -]*{$this->preg_implode($this->t("Fax"))}|$)#");
        }

        $fax = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Fax"))}]", null, true, "#{$this->preg_implode($this->t("Fax"))}[.]?\s*({$patterns['phone']})[-\s]*$#");

        if (empty($fax) && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Fax"))}]", null, true, "#{$this->preg_implode($this->t("Fax"))}[.]?[-\s]*$#"))) {
            $fax = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Hotel booked:"))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains($this->t("Fax"))}]/following::text()[normalize-space()][1]", null, true, "#^\s*({$patterns['phone']})[-\s]*$#");
        }

        $accounts = $this->http->FindNodes("//text()[" . $this->eq($this->t("MeliáRewards card:")) . "]/ancestor::td[1]/following-sibling::td[1]");

        $segments = [];
        $segmentsCount = $this->http->XPath->query("//text()[" . $this->eq($this->t("Arrival date:")) . "]")->length;

        if ($segmentsCount === 1) {
            $segments[][] = null;
        } elseif ($segmentsCount > 1) {
            $xpath = "//text()[" . $this->eq($this->t("Arrival date:")) . "]/ancestor::*[" . $this->starts($this->t("Room ")) . " and descendant::text()[normalize-space()][position() < 5][" . $this->eq($this->t("Personal details:")) . "] and count(.//text()[" . $this->eq($this->t("Arrival date:")) . "]) = 1][1]";
//            $this->logger->debug('$xpath = '.print_r( $xpath,true));
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $arrivalDate = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival date:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));
                $departureDate = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure date:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));
                $cancelled = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancelada")) . "]", $root);
                $segments[$arrivalDate . $departureDate . (!empty($cancelled) ? '_c' : '')][] = $root;
            }
        }

        if (count($segments) > 1) {
            $totalStr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total cost including taxes")) . "]" .
                "[not(preceding::text()[" . $this->eq(preg_replace("/(.+)/", '$1#', $this->t("Room ")), "translate(normalize-space(), '0123456789', '##########')") . "])]/ancestor::td[1]/following-sibling::td[1]", $root);
            if (empty($totalStr)) {
                $totalStr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total cost not including taxes")) . "]" .
                    "[not(preceding::text()[" . $this->eq(preg_replace("/(.+)/", '$1#', $this->t("Room ")), "translate(normalize-space(), '0123456789', '##########')") . "])]/ancestor::td[1]/following-sibling::td[1]", $root);
            }

            if (preg_match("#^\s*(" . $this->preg_implode($this->t("points")) . "\s*\d[\d\., ]*)\s*(\+|$)#i", $totalStr, $m)
                || preg_match("#^\s*(\d[\d\., ]*\s*" . $this->preg_implode($this->t("points")) . ")\s*(\+|$)#i", $totalStr, $m)
            ) {
                $email->price()
                        ->spentAwards($m[1])
                    ;
            }

            if (preg_match("#(?:^|\+)\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalStr, $m)
                || preg_match("#(?:^|\+)\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $totalStr, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $total = (float) PriceHelper::parse($m['amount'], $currencyCode);
                $email->price()
                        ->total($total)
                        ->currency($currency)
                    ;
            }
        }

        foreach ($segments as $roots) {
            // General
            $h = $email->add()->hotel();
            $h->general()
                ->confirmation($conf)
            ;

            if (!empty($status)) {
                $h->general()
                    ->status($status);
            }

            if ($isCancelled === true) {
                $h->general()
                    ->status('Cancelled')
                    ->cancelled()
                ;
            }

            if (!empty($accounts)) {
                $h->program()->accounts(array_unique($accounts), false);
            }

            $h->hotel()
                ->name($hotelName)
                ->address($hotelAddress)
                ->phone($phone, true, true)
                ->fax($fax, true, true)
            ;

            $rooms = 0;
            $emptyTypes = true;

            if (count($roots) == 1) {
                $rooms = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Number of rooms:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $roots[0], true, "#^(\d+)#");

                if (!empty($rooms)) {
                    $emptyTypes = false;
                    $type = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Category:")) . "]/ancestor::td[1]/following-sibling::td[1]");

                    for ($i = 0; $i < $rooms; $i++) {
                        $h->addRoom()
                            ->setType($type, true, true);
                    }
                }
            }

            if (empty($rooms)) {
                $rooms = count($roots);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrival date:")) . "]/ancestor::td[1]/following-sibling::td[1]", $roots[0])))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure date:")) . "]/ancestor::td[1]/following-sibling::td[1]", $roots[0])))
                ->rooms($rooms);

            $guests = $kids = null;
            $cancellations = [];

            $spentAwards = 0;
            $total = 0.0;

            $travellers = [];

            foreach ($roots as $root) {
                $loc = $this->http->FindSingleNode(".//a[contains(@href, '&localizer=') and contains(@href, '.melia.com')]/@href", $root, true, "/&localizer=(\d{5,})/");

                if (!empty($loc) && !in_array($loc, array_column($h->getConfirmationNumbers(), 0))) {
                    $h->general()
                        ->confirmation($loc, 'localizer');
                }

                if (!empty($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancelada")) . "]", $root))) {
                    $h->general()
                        ->status($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancelada")) . "]", $root))
                        ->cancelled();
                }

                $cancellation = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancellation of the reservation")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]", $root);

                if (empty($cancellation)) {
                    $cancellation = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancellation of the reservation")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]", $root);
                }

                if (empty($cancellation)) {
                    $cancellation = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Cancellation Policy:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);
                }

                if (empty($cancellation)) {
                    $cancellation = implode(', ', $this->http->FindNodes(".//text()[" . $this->eq($this->t("Cancellation of the reservation")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/descendant::p[1]", $root));
                }

                $cancellations[] = $cancellation;

                $guest = $kid = null;
                $guestsRows = $this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t("Room "))}] and *[normalize-space()][2][{$this->contains($this->t("adult"))} or {$this->contains($this->t("children"))}] ]", $root);

                foreach ($guestsRows as $i => $gRow) {
                    $phrases = array_map(function ($item) use ($i) {
                        return $item . ($i + 1);
                    }, (array) $this->t("Room "));

                    $guest = $this->http->FindSingleNode("descendant::text()[{$this->starts($phrases)}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t("adult"))}]", $gRow, true, "#\b(\d{1,3})\s*{$this->preg_implode($this->t("adult"))}#u");

                    if ($guest) {
                        $guests += $guest;
                    }

                    $kid = $this->http->FindSingleNode("descendant::text()[{$this->starts($phrases)}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t("children"))}]", $gRow, true, "#\b(\d{1,3})\s*{$this->preg_implode($this->t("children"))}#u");

                    if ($kid) {
                        $kids += $kid;
                    }
                }

                if ($guest === null) {
                    $guest = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Occupancy:"))}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t("adult"))}]", $root, true, "#\b(\d{1,3})\s*{$this->preg_implode($this->t("adult"))}#u");

                    if ($guest) {
                        $guests += $guest;
                    }
                }

                if ($kid === null) {
                    $kid = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Occupancy:"))}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t("children"))}]", $root, true, "#\b(\d{1,3})\s*{$this->preg_implode($this->t("children"))}#u");

                    if ($kid) {
                        $kids += $kid;
                    }
                }

                if ($emptyTypes) {
                    $type = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Category:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);
                    $h->addRoom()
                        ->setType($type, true, true)
                    ;
                }

                // Price
                $totalStr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Total cost including taxes"))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(.*\d.*?)(?:\s*\(|$)/');
                if (empty($totalStr)) {
                    $totalStr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Total cost not including taxes"))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(.*\d.*?)(?:\s*\(|$)/');
                }

                if ($totalStr === null) {
                    $room = $this->http->FindSingleNode("(.//descendant::text()[normalize-space()])[1]", $root);

                    if (preg_match("/^\s*(" . $this->preg_implode($this->t("Room ")) . "\s*)(\d+)\s*$/u", $room, $m)) {
                        $nextRoom = $m[1] . (string) ($m[2] + 1);
                        $totalStr = $this->http->FindSingleNode("following::text()[{$this->starts($this->t("Total cost including taxes"))} and not(preceding::text()[{$this->eq($nextRoom)}])]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(.*\d.*?)(?:\s*\(|$)/');
                        if (empty($totalStr)) {
                            $totalStr = $this->http->FindSingleNode("following::text()[{$this->starts($this->t("Total cost not including taxes"))} and not(preceding::text()[{$this->eq($nextRoom)}])]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^(.*\d.*?)(?:\s*\(|$)/');
                        }
                    }
                }
                if ($totalStr === null &&$segmentsCount === 1 && count($roots) === 1) {
                    $totalStr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total cost including taxes")) . "]" .
                        "[not(preceding::text()[" . $this->eq(preg_replace("/(.+)/", '$1#', $this->t("Room ")), "translate(normalize-space(), '0123456789', '##########')") . "])]/ancestor::td[1]/following-sibling::td[1]", $root);
                    if (empty($totalStr)) {
                        $totalStr = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total cost not including taxes")) . "]" .
                            "[not(preceding::text()[" . $this->eq(preg_replace("/(.+)/", '$1#', $this->t("Room ")), "translate(normalize-space(), '0123456789', '##########')") . "])]/ancestor::td[1]/following-sibling::td[1]", $root);
                    }
                }

                if (preg_match("#^\s*(?<currency>{$this->preg_implode($this->t("points"))})\s*(\d[,.\'\d ]*)\s*(\+|$)#i", $totalStr, $m)
                    || preg_match("#^\s*(?<amount>\d[,.\'\d ]*)\s*(?<currency>{$this->preg_implode($this->t("points"))})\s*(\+|$)#i", $totalStr, $m)
                ) {
                    $spentAwards += preg_replace("/\D/", '', $m['amount']);
                    $PointsCurrency = $m['currency'];
                }

                if (preg_match("/(?:^|\+)\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d ]*)\s*$/", $totalStr, $m)
                    || preg_match("/(?:^|\+)\s*(?<amount>\d[,.\'\d ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalStr, $m)
                ) {
                    $currency = $this->currency($m['currency']);
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $total += (float) PriceHelper::parse($m['amount'], $currencyCode);
                }

                $travellers[] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Personal details:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);
            }

            $cancellations = array_unique(array_filter($cancellations));

            if (!empty($cancellations)) {
                $h->general()
                    ->cancellation(implode(". ", $cancellations), true, true);

                $this->detectDeadLine($h);
            }

            if (empty($guests)) {
                $guests = null;
            }

            if (empty($kids)) {
                $kids = null;
            }
            $h->booked()
                ->guests($guests, false, $h->getCancelled())
                ->kids($kids, false, true)
            ;

            if (!empty($spentAwards)) {
                $h->price()
                    ->spentAwards($spentAwards . ' ' . $PointsCurrency);
            }

            if (!empty($total)) {
                $h->price()
                    ->total($total)
                    ->currency($currency)
                ;
            }

            $h->general()
                ->travellers(array_unique($travellers));
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#If cancel within (\d+) Hour\(s\) before arrival or guest is no show, 1 Night\(s\) will be charged as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#If cancel within (\d+) Hour\(s\) before arrival or customers are no show, (?:\d+ %|\d Night\(s\)) will be charged as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#If you cancel (\d+) hour\(s\) before arrival or are a no-show 1 night\(s\) charge as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#Se cancelar (\d+) Hora\(s\) antes da chegada ou não comparência, 1 Noite\(s\) do total será cobrado como penalidade#i", $cancellationText, $m) // pt
            || preg_match("#Se cancelar (\d+) Hora\(s\) antes da chegada ou não comparecer, 1 Noite\(s\) será cobrada como gastos de cancelamento#i", $cancellationText, $m) // pt
            || preg_match("#Se cancela a reserva (\d+) Hora\(s\) antes da chegada ou nao se apresenta, 1 Noite\(s\) sera cobrado gastos de cancelaçao#i", $cancellationText, $m) // pt
            || preg_match("#Bei Stornierungen (\d+) Stunde\(n\) vor Anreise oder NoShow werden 1 Nacht\(¨-e\) der Gesamtsumme berechnet#i", $cancellationText, $m) // de
            || preg_match("#Si cancela (\d+) Hora\(s\) antes de la llegada o no se presenta: 1 Noche\(s\) de cargo como gastos de cancelación#i", $cancellationText, $m) // es
        ) {
            $h->booked()->deadlineRelative($m[1] . " hours");
        } elseif (preg_match("/If (?i)cancell? within (?<prior>\d{1,3})\s*Day\(s\) before arrival or customers are no show, (?:\d+ %|\d Night\(s\)) will be charged as cancell?ation penalty/", $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        } elseif (
               preg_match("#Wenn sie die Buchung nach (\d.*?) am Anreisetag stornieren, oder nicht erscheinen fallen 1 Uebernachtung des Gesamtbetrags als Storierungskosten an.#i", $cancellationText, $m) // de
            || preg_match("#Si cancela después(?: de)? (\d.*?) del dia de llegada o no se presenta,? 1 noche\(s\) de cargo como gastos de cancelación#i", $cancellationText, $m) // es
            || preg_match("#Si vous annulez après (\d.*?) du jour d'arrivée ou ne vs présentez pas 1 Nuit\(s\) vous sera débité en frais d'annulation#i", $cancellationText, $m) // fr
            || preg_match("#If cancel after (\d.*?) of arrival day or customers are no show, 1 Night\(s\) will be charged as cancellation penalty#i", $cancellationText, $m) // en
        ) {
            $h->booked()->deadlineRelative("0 day", $this->normalizeTime($m[1]));
        } elseif (
               preg_match("#If cancel after (.+?) of arrival day or guest is no show, \d+ % will be charged as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#If cancel or modify the booking or guest is no show, \d+ % will be charged as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#If cancel or modify the booking or customers are no show, \d+ % will be charged as cancellation penalty#i", $cancellationText, $m) // en
            || preg_match("#Si cancela o modifica la reserva o no se presenta, \d+ % de cargo como gastos de cancelación#i", $cancellationText, $m) // es
            || preg_match("#Se cancelar, modificar a reserva ou não comparência \d+ % do total será cobrado como penalidade#i", $cancellationText, $m) // pt
        ) {
            $h->booked()->nonRefundable();
        }
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
        //$this->logger->debug($str);
        $in = [
            // Sexta-feira, 24 Mayo 2019. Check-out hasta las 12:00h    |    Thursday, 11 April 2019. Check-out until 12:00 noon
            "#^[[:alpha:]-]+, (\d{1,2} [[:alpha:]]+ \d{4})\. [^\d]* (\d{1,2})[:.]+(\d{2}(?:\s*[ap]m)?)h?(?:\s*[^\d\s]{3,10})?$#iu",
            // Donnerstag, 08 Mai 2014. Check-out bis
            "#^[[:alpha:]-]+, (\d{1,2} [[:alpha:]]+ \d{4})\. [^\d]*$#u",
            //Friday, 12 July 2019. Check-in after 3pm
            "#^\w+[,]\s+(\d+\s+\w+\s+\d+)[.].+\s+(\d+\s*(?:am|pm))#iu",
        ];
        $out = [
            "$1, $2:$3",
            "$1",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime($time)
    {
        $in = [
            "#^\s*(\d{1,2})\s*([ap]m)\s*$#i", // 18 PM
        ];
        $out = [
            "$1:00 $2",
        ];
        $time = preg_replace($in, $out, $time);

        if (preg_match("#^\s*((\d+):\d+)\s*[ap]m\s*$#i", $time, $m) && $m[2] > 12) {
            // 18:00 PM -> 18:00
            $time = $m[1];
        }

        return $time;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
