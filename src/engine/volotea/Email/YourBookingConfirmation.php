<?php

namespace AwardWallet\Engine\volotea\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingConfirmation extends \TAccountChecker
{
    // +1 bcd - de
    public $mailFiles = "volotea/it-11432566.eml, volotea/it-60785833.eml, volotea/it-6338647.eml, volotea/it-6340461.eml, volotea/it-6340928.eml, volotea/it-6342887.eml, volotea/it-6344903.eml, volotea/it-6352771.eml, volotea/it-6355380.eml, volotea/it-6365540.eml, volotea/it-6386922.eml, volotea/it-6399301.eml, volotea/it-6555145.eml, volotea/it-6555148.eml";

    public $reSubject = [
        'de' => ['Volotea • Ihre Buchungsbestätigung'],
        'it' => ['Volotea • Conferma della tua prenotazione'],
        'fr' => ['Volotea • Confirmation de reservation', 'Volotea • Confirmation de réservation'],
        'es' => ['Volotea • Confirmación de tu reserva'],
        'en' => ['Volotea • Your booking confirmation'],
        'el' => ['Volotea • Η επιβεβαίωση της κράτησής σας'],
    ];

    public $lang = '';

    public $reBody = 'Volotea';
    public $reBody2 = [
        'de' => 'Hinflug',
        'it' => 'Partenza',
        'fr' => 'Départ',
        'es' => 'Salida',
        'en' => 'Departure',
        'el' => 'αναχώρηση',
    ];

    public static $dictionary = [
        'de' => [
            "Confirmation No.:" => ["Bestätigungsnr.:"],
            "Passenger"         => ["Passagier"],
            "Outbound"          => "Hinflug",
            //"Return" => "",
            "Fare"           => "Tarif",
            "Baggage:"       => "Gepäck",
            "Total Amount:"  => ["Gesamtpreis"],
            "Departure:"     => "Abflug:",
            "Date:"          => "Datum:",
            "Flight number:" => "Flugnummer:",
            //"Departure terminal:" => "",
            //"Arrival terminal:" => "",
            "Arrival:" => ["Ankunft:"],
            //"Booking date:" => "",
            "Status:" => "Status:",
        ],
        'it' => [
            "Confirmation No.:"  => ["N. di conferma:", "Il tuo numero di conferma è"],
            "Passenger"          => ["Passeggero", "PASSEGGERI"],
            "Outbound"           => "Andata",
            "Return"             => "Ritorno",
            "Fare"               => "Tariffa",
            "Baggage:"           => "NOTTRANSLATED",
            "Total Amount:"      => ["Prezzo totale:", "PREZZO TOTALE:", "Pagamento dell'Importo:"],
            "Departure:"         => "Partenza:",
            "Date:"              => "Data:",
            "Flight number:"     => "Numero del volo:",
            "Departure terminal:"=> "NOTTRANSLATED",
            "Arrival terminal:"  => "NOTTRANSLATED",
            "Arrival:"           => ["Arrivo a:", "Arrivo:"],
            "Booking date:"      => ["Data di prenotazione:", "Data di prenotazione :"],
            "Status:"            => "Stato:",
            "Seats"              => "Sedili",
        ],
        'fr' => [
            "Confirmation No.:"  => ["N° de confirmation:", "NUMERO DE CONFIRMATION"],
            "Passenger"          => ["Passager", "PASSAGERS"],
            "Outbound"           => "Aller",
            "Return"             => "Retour",
            "Fare"               => "Tarif",
            "Baggage:"           => "Bagages:",
            "AGGIUNGERE SERVIZI" => "AJOUTER SERVICES",
            "Total Amount:"      => ["Total:", "Montant du paiement :"],
            "Departure:"         => "Départ:",
            "Date:"              => "Date:",
            "Flight number:"     => "Numéro de vol:",
            "Departure terminal:"=> "Terminal de départ:",
            "Arrival terminal:"  => "Terminal d'arrivée:",
            "Arrival:"           => "Arrivée:",
            "Booking date:"      => ["Date de réservation:", "Date de réservation :"],
            "Status:"            => "Statut:",
            "Seats"              => ["Sièges", "Sièges:"],
        ],
        'es' => [
            "Confirmation No.:"  => ["Nº de confirmación:", "Tu número de confirmación es"],
            "Passenger"          => "Pasajero",
            "Outbound"           => "Ida",
            "Return"             => "Llegada",
            "Fare"               => "Tarifa",
            "Baggage:"           => ["Precio:", "Descuento:", "Tarifa:", "Asiento:"],
            "Total Amount:"      => ["Precio Total:", "Cantidad de pago:"],
            "Departure:"         => "Salida:",
            "Date:"              => "Fecha:",
            "Flight number:"     => "Número de vuelo:",
            "Departure terminal:"=> "NOTTRANSLATED",
            "Arrival terminal:"  => "NOTTRANSLATED",
            "Arrival:"           => "Llegada:",
            "Booking date:"      => ["Fecha de reserva:", "Fecha de reserva :"],
            "Status:"            => "Estado:",
        ],
        'en' => [
            "Confirmation No.:"=> ["Confirmation No.:", "Confirmation No.", "Your booking confirmation number is"],
            "Total Amount:"    => ["Total Amount:", "Total amount:"],
            "Booking date:"    => ["Booking date:", "Booking date :"],
        ],

        'el' => [
            "Confirmation No.:" => "Σε επιβεβαίωση:",
            "Passenger"         => "επιβάτης",
            "Outbound"          => "επιβάτης",
            //"Return"=>"",
            "Fare" => "ναύλος",
            //"Baggage:"=>"",
            "Total Amount:"  => "Σύνολο:",
            "Departure:"     => "αναχώρηση:",
            "Date:"          => "ημερομηνία:",
            "Flight number:" => "αριθμός πτήσης:",
            //"Departure terminal:"=>"",
            //"Arrival terminal:"=>"",
            "Arrival:" => "άφιξη:",
            //"Booking date:"=>"",
            "Status:" => "κατάσταση:",
            "Seats"   => "Καθίσματα:",
        ],
    ];

    public function parseHtml(Email $email): void
    {
        $f = $email->add()->flight();

        $headerRoots = $this->http->XPath->query('(//text()[' . $this->starts($this->t('Confirmation No.:')) . ']/ancestor::table[1])[1]');

        if ($headerRoots->length > 0) {
            $headerRoot = $headerRoots->item(0);
        } else {
            $headerRoot = $this->http->XPath->query('/descendant::body')->item(0);
        }

        // RecordLocator
        $f->general()
            ->confirmation($this->nextText($this->t("Confirmation No.:"), $headerRoot));

        // TotalCharge
        // Currency
        $payment = $this->nextText($this->t("Total Amount:"));

        if ($payment) {
            $f->price()
                ->total($this->amount($payment))
                ->currency($this->currency($payment));
        }

        // TripSegments
        $xpath = "//text()[{$this->starts($this->t("Departure:"))}]/ancestor::*[self::td or self::table or self::tbody][{$this->contains($this->t("Arrival:"))}][1]"; // it-6399301
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "./descendant::text()[" . $this->eq($this->t("Departure:")) . "]/ancestor::tr[1]/..";
            $segments = $this->http->XPath->query($xpath, $headerRoot);
        }
        $flightNumbers = [];

        foreach ($segments as $root) {
            // AirlineName
            // FlightNumber
            $flight = $this->nextText($this->t("Flight number:"), $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $matches)) {
                if (count($flightNumbers) === 0) {
                    $seg = $f->addSegment();
                } else {
                    if (array_search($matches['number'], $flightNumbers) === false) {
                        $seg = $f->addSegment();
                    } else {
                        continue;
                    }
                }

                $seg->airline()
                    ->name($matches['name'])
                    ->number($matches['number']);

                $flightNumbers[] = $matches['number'];
            } else {
                $this->logger->debug('Flight number not found!');

                return;
            }

            $date = $this->nextText($this->t("Date:"), $root);

            if (empty($date) || strlen($date) < 6) {
                $date = $this->http->FindSingleNode("./descendant::tr[contains(normalize-space(), 'Date')]", $root, true, "/{$this->opt($this->t('Date:'))}\s*(\d+\s*\w+\s*\d{4})/");
            }

            $date = strtotime($this->normalizeDate($date));

            $selfcol = count($this->http->FindNodes("./preceding-sibling::td")) + 1; // it-6399301

            // DepName
            if (!$depName = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/td[{$selfcol}]//text()[" . $this->contains("·") . "]", $root, true, "#(.*?)\s+·\s+#")) {
                if (!$depName = $this->http->FindSingleNode("./tr[" . $this->contains("·") . "][1]", $root, true, "#(.*?)\s+·\s+#")) {
                    $depName = $this->http->FindSingleNode("./preceding::text()[" . $this->contains("•") . "][1]", $root, true, "#(.*?)\s+•\s+#");
                }
            }

            if (!empty($depName)) {
                $seg->departure()->name($depName);
            }

            // DepartureTerminal
            $terminalDep = $this->nextText($this->t("Departure terminal:"), $root);

            if ($terminalDep) {
                $seg->departure()
                    ->terminal($terminalDep);
            }

            // DepDate
            $depDate = strtotime($this->nextText($this->t("Departure:"), $root), $date);

            if (!empty($depDate)) {
                $seg->departure()
                    ->date($depDate);
            }

            // ArrName
            if (!$arrName = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/td[{$selfcol}]//text()[" . $this->contains("·") . "]", $root, true, "#\s+·\s+(.+)#")) {
                if (!$arrName = $this->http->FindSingleNode("./tr[" . $this->contains("·") . "][1]", $root, true, "#\s+·\s+(.+)#")) {
                    $arrName = $this->http->FindSingleNode("./preceding::text()[" . $this->contains("•") . "][1]", $root, true, "#\s+•\s+(.+)#");
                }
            }

            if (!empty($arrName)) {
                $seg->arrival()
                    ->name($arrName);
            }

            // ArrivalTerminal
            $terminalArr = $this->nextText($this->t("Arrival terminal:"), $root);

            if ($terminalArr) {
                $seg->arrival()
                    ->terminal($terminalArr);
            }

            // ArrDate
            $arrDate = strtotime($this->nextText($this->t("Arrival:"), $root), $date);

            if ($arrDate < $depDate) {
                $arrDate = strtotime("+1 day", $arrDate);
            }

            if (!empty($arrDate)) {
                $seg->arrival()
                    ->date($arrDate);
            }

            // DepCode
            // ArrCode
            $seg->departure()
                ->noCode();

            $seg->arrival()
                ->noCode();

            $seats = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::table[1]/descendant::tr[{$this->contains($this->t('Fare'))}]/td[2]/following::text()[{$this->starts($this->t('Seats'))}]/following::text()[normalize-space()][1]");

            if (count(array_filter($seats)) === 0) {
                $seats = $this->http->FindNodes("//text()[{$this->starts($this->t('Seats'))}]/following::text()[normalize-space()][1]", null, "/\s*(\d[A-Z])\s*/");
            }

            if (count(array_filter($seats)) > 0) {
                $seg->extra()
                    ->seats(array_filter(array_unique($seats)));
            }
        }

        // ReservationDate
        $reservationDate = $this->nextText($this->t("Booking date:"));

        if ($reservationDate) {
            $f->general()
                ->date(strtotime($this->normalizeDate($reservationDate)));
        }

        // Status
        $status = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t('Status:')) . ' and not(.//td)]/following-sibling::td[string-length(normalize-space(.))>2][1]', $headerRoot);

        if (!$status) {
            $status = $this->nextText($this->t("Status:"));
        }

        if ($status) {
            $f->general()
                ->status($status);
        }

        // Passengers
        $passengers = $this->http->FindNodes('/descendant::tr[ ./td[2][' . $this->contains($this->t("Passenger")) . '] and (./td[4][' . $this->contains($this->t("Outbound")) . '] or ./td[6][' . $this->contains($this->t("Return")) . ']) ][1]/following-sibling::tr[ ./td[normalize-space(.)][last()][ ./descendant::text()[' . $this->eq($this->t("Fare")) . '] ] ]/td[2]');

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1][normalize-space(.)]"));
        }

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Baggage:")) . "]/ancestor::tr[./td[3][normalize-space(.)]][1]/td[1]/descendant::text()[normalize-space(.)][1]"));
        }

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes("//tr[" . $this->eq($this->t("Passenger")) . "]/following-sibling::tr[./following-sibling::tr[" . $this->contains($this->t("AGGIUNGERE SERVIZI")) . "]]"));
        } // it-6399301

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::table[1]/descendant::tr[{$this->contains($this->t('Fare'))}]/td[2]"));
        }

        $f->general()
            ->travellers($passengers);
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Volotea') !== false
            || stripos($from, '@info.volotea.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        $body = html_entity_decode($parser->getHTMLBody());

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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(html_entity_decode($parser->getHTMLBody()));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^(\d+\s+[^\d\s]+\s+\d{4})$#", //19 July 2017
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\.\,]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
