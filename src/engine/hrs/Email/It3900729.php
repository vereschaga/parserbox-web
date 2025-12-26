<?php

namespace AwardWallet\Engine\hrs\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3900729 extends \TAccountCheckerExtended
{
    public $mailFiles = "hrs/it-765593399.eml";

    public $reBody = 'HRS';

    public $pdfNamePattern = ".*\.pdf.*";

    public $reBody2 = [
        "en"  => "Your booking",
        'en2' => 'Information about the reservation',
        "de"  => "Ihre Buchung",
        "fr"  => "Nous vous remercions d’avoir réservé sur",
        "nl"  => "Uw reserveringsgegevens",
        'pt'  => 'Os dados da sua reserva',
        'es'  => 'Datos de reserva',
        'pl'  => 'Data rezerwacji:',
        'it'  => 'I dati della vostra prenotazione',
    ];

    public static $dictionary = [
        "en" => [
            "Total room price:"     => ["Total room price:", "Total room price (incl. tax):"],
            "Cancelation deadline:" => ["Cancelation deadline:", "Cancellation deadline:"],
            "hotel category",
        ],
        "it" => [
            "Reservation number:"             => "Numero prenotazione.:",
            "Arrival / Departure:"            => "Arrivo / Partenza:",
            "Your chosen hotel"               => "L'hotel da voi scelto",
            "Earliest check-in (Local time):" => 'Primo check-in (Ora locale):',
            "Telephone | Fax:"                => "Telefono | Fax:",
            "Arriving guests:"                => "Name booker:",
            "Cancelation deadline:"           => "Termine ultimo di cancellazione:",
            "Total room price:"               => "Prezzo totale:",
            "Total room price (incl. tax):"   => "Prezzo totale:",
            "Reservation date:"               => "Data della prenotazione:",
        ],
        "de" => [
            "Reservation number:"             => "Buchungsnummer:",
            "Arrival / Departure:"            => "Anreise / Abreise:",
            "Your chosen hotel"               => "Ihr ausgewähltes Hotel",
            "Earliest check-in (Local time):" => 'Frühester Check-In (Ortszeit):',
            "Telephone | Fax:"                => "Telefon | Fax:",
            "Arriving guests:"                => "Anreisende Gäste:",
            "Cancelation deadline:"           => "Stornierungsfrist:",
            "Total room price:"               => "Zimmer-Gesamtpreis:",
            "Total room price (incl. tax):"   => "Gesamtpreis:",
            "Reservation date:"               => "Buchungsdatum:",
        ],
        "fr" => [
            "Reservation number:"             => "Numéro de réservation:",
            "Arrival / Departure:"            => "Arrivée / Départ:",
            "Your chosen hotel"               => "L'hôtel sélectionné",
            "Earliest check-in (Local time):" => 'Frühester Check-In (Ortszeit):',
            "Telephone | Fax:"                => "Téléphone | Télécopie",
            "Arriving guests:"                => "Client(s) logeant à l'hôtel:",
            "Cancelation deadline:"           => "Délai d'annulation:",
            "Total room price:"               => "Prix total du séjour (taxes comprises):",
            "Reservation date:"               => "Date de la réservation :",
        ],
        "nl" => [
            "Reservation number:"             => "Reserveringsnummer:",
            "Arrival / Departure:"            => "Aankomst / Vertrek:",
            "Your chosen hotel"               => "Uw gekozen hotel",
            "Earliest check-in (Local time):" => 'Check-in vanaf (Plaatselijke tijd):',
            "Telephone | Fax:"                => "Telefoon | Fax:",
            "Arriving guests:"                => "Aankomende gasten:",
            "Cancelation deadline:"           => "Annuleringstermijn:",
            "Total room price:"               => "Totaalprijs voor de kamer (incl. belastingen):",
            "Reservation date:"               => "Reserveringsdatum:",
        ],
        "pt" => [
            "Reservation number:"             => "Número de reserva:",
            "Arrival / Departure:"            => "Chegada / Partida:",
            "Your chosen hotel"               => "O seu hotel escolhido",
            "Earliest check-in (Local time):" => 'Check-In mais cedo (Hora local):',
            "Telephone | Fax:"                => "Telefone | Fax:",
            "Arriving guests:"                => "Hóspedes a chegar:",
            "Cancelation deadline:"           => "Prazo para cancelamento:",
            "Total room price:"               => "Preço total do quarto:",
            "Reservation date:"               => "Data de reserva:",
        ],
        "es" => [
            "Reservation number:"  => "Número de reserva:",
            "Arrival / Departure:" => "Llegada / Salida:",
            "Your chosen hotel"    => "Datos de reserva",
            //            "Earliest check-in (Local time):" => '',
            //            "Telephone | Fax:" => "Teléfono:",
            'Telephone:'                    => 'Teléfono:',
            "Arriving guests:"              => "Llegada de clientes:",
            "Cancelation deadline:"         => "Plazo de anulación:",
            "Total room price:"             => "Precio global de habitación",
            "Reservation date:"             => "Fecha de reserva:",
            'Total room price (incl. tax):' => 'Precio global de habitación (con impuestos):',
            'Reservation cancelled'         => 'Reserva cancelada',
            'Reservation'                   => 'Reserva',
            'Cancellation data'             => 'Datos de anulación',
            'HRS process number:'           => 'Nº de reserva HRS:',
        ],
        "pl" => [
            "Reservation number:"  => "Numer rezerwacji:",
            "Arrival / Departure:" => "Przyjazd / Wyjazd:",
            "Your chosen hotel"    => "Wybrany hotel",
            //            "Earliest check-in (Local time):" => '',
            "Telephone | Fax:"              => "Telefon:",
            'Telephone:'                    => 'Telefon:',
            "Arriving guests:"              => "Przyjeżdzający goście:",
            "Cancelation deadline:"         => "Termin anulacji:",
            "Total room price:"             => "Cena całkowita pokoju",
            "Reservation date:"             => "Data rezerwacji:",
            'Total room price (incl. tax):' => 'Cena całkowita pokoju (z podatkami):',
            //            'Reservation cancelled' => '',
            'Reservation' => 'Rezerwacji',
            //            'Cancellation data' => '',
            'HRS process number:' => 'HRS Numer procesu:',
        ],
    ];

    public $lang = "en";

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            return false;
        }

        $body = $parser->getHTMLBody();

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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $email->setType('HrsHotel' . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//*[normalize-space(text())='" . $this->t("Reservation number:") . "']/ancestor::tr[1]/..";
        //			$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->re('/(\d{5,12})[ ]*/', $this->getField($this->t("Reservation number:"), $root)));

            if ($status = $this->http->FindSingleNode("(preceding::text()[{$this->contains($this->t('Reservation cancelled'))}][1])[1]", $root, true, "/{$this->t('Reservation')} (\w+)/")) {
                $h->setStatus($status)
                    ->setCancelled(true);

                if ($cNum = $this->http->FindSingleNode("//tr[({$this->starts($this->t('Cancellation data'))}) and not(.//tr)]/following-sibling::tr[normalize-space(.)][{$this->contains($this->t('HRS process number:'))}][1]", null, true, "/{$this->t('HRS process number:')}[ ]+(\d+)/")) {
                    $h->setCancellationNumber($cNum);
                }
            }

            $name = $this->http->FindSingleNode("//img[contains(normalize-space(@alt), '{$this->t('hotel category')}') or contains(@src, '/images/stars/')]/preceding::a[1]");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '* * * HRS stars')][1]", null, true, '/(.+) \(\* \* \* HRS stars\)/');
            }

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'HRS hotel no') and not(.//td)]/descendant::text()[normalize-space(.)][1]");
            }

            if (!empty($name)) {
                $h->hotel()
                    ->name($name);
            }

            $address = $this->http->FindSingleNode("//img[contains(normalize-space(@alt), '{$this->t('hotel category')}') or contains(@src, '/images/stars/')]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]");

            if (empty($address)) {
                $address = implode(', ', $this->http->FindNodes("//text()[contains(normalize-space(.), '* * * HRS stars')][1]/following::text()[normalize-space(.)][position() < 4]"));
            }

            if (empty($address)) {
                $address = implode(', ', $this->http->FindNodes("//td[contains(normalize-space(.), 'HRS hotel no') and not(.//td)]/descendant::text()[normalize-space(.)][position() > 1 and position() < 5]"));
            }

            if (20 < strlen($address)) {
                $h->hotel()
                    ->address($address);
            } elseif (empty($name)) {
                $h->hotel()
                    ->name($this->next($this->t("Your chosen hotel")))
                    ->address($this->next($this->t("Your chosen hotel"), null, 2));
            }

            $checkInTime = $this->getField($this->t("Earliest check-in (Local time):"));

            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->part(" - ", $this->getField($this->t("Arrival / Departure:"), $root), 0)) . ', ' . $checkInTime))
                ->checkOut(strtotime($this->normalizeDate($this->part(" - ", $this->getField($this->t("Arrival / Departure:"), $root), 1)) . ', ' . $checkInTime));

            $phone = $this->part(" | ", $this->getField($this->t("Telephone | Fax:")), 0);

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), '{$this->t('Telephone:')}')][1])[1]", null, true, "/{$this->t('Telephone:')}[ ]+([\d\-\+\(\) ]+)/");
            }

            $fax = $this->part(" | ", $this->getField($this->t("Telephone | Fax:")), 1);
            $h->hotel()
                ->phone($phone)
                ->fax((strlen($fax) > 5) ? $fax : null, true, true);

            $h->addTraveller($this->getField($this->t("Arriving guests:"), $root));

            $cancel = $this->http->FindSingleNode(".//text()[" . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, (array) $this->t("Cancelation deadline:"))) . "]/following::text()[normalize-space(.)][1]/..", $root);

            if (!empty($cancel)) {
                $h->general()
                    ->cancellation($cancel);
            }

            if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{2,4}, \d{1,2}:\d{2}) horas \(locais do hotel\) gratuitamente/ui', $cancel, $m)) {
                $h->booked()
                    ->deadline(strtotime($m[1]));
            } elseif (
                preg_match('/La anulación solo es posible hasta las (?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{2,4}), (?<time>\d{1,2}\:\d{2}) horas \(hora local del hotel\)/', $cancel, $m)
                || preg_match('/È possibile annullare gratuitamente questa prenotazione entro il (?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{2,4}) (?<time>\d{1,2}\:\d{2})\s*\(/', $cancel, $m)
            ) {
                $h->booked()
                    ->deadline(strtotime($m['year'] . '-' . $m['month'] . '-' . $m['day'] . ', ' . $m['time']));
            } elseif (preg_match('/(?:Cancellation is free of cost|This booking can be cancelled free of charge) until (?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{2,4}), (?<time>\d{1,2}\:\d{2}) \(Hotel-local time\)/', $cancel, $m)) {
                $h->booked()
                    ->deadline(strtotime($m['year'] . '-' . $m['month'] . '-' . $m['day'] . ', ' . $m['time']));
            }

            $r = $h->addRoom();
            $r->setType($this->http->FindSingleNode("./preceding::text()[contains(translate(., '1234567890', 'dddddddddd'), 'd.')][1]", $root, true, "#\d+\.\s+(.*?)\s*(?:\:|\(\w+\))\s*#"), true, true);

            $h->booked()
                ->rooms(1);

            $rates = $this->http->FindNodes("//text()[contains(normalize-space(.), 'incl. colazione (')]/ancestor::tr[1]");
            $rateArray = [];

            foreach ($rates as $rate) {
                $this->logger->debug($rate);

                if (preg_match("/(?<dateRange>\d+\.\d+\.\s*\-\s*\d+\.\d+\.).+\s(?<price>[\d\.\,\']+\s*[A-Z]{3})/", $rate, $m)) {
                    $rateArray[] = $m['dateRange'] . ' - ' . $m['price'];
                }
            }

            if (count($rateArray) > 0) {
                $r->setRate(implode(', ', $rateArray));
            }

            $total = cost(orval(
                $this->getField($this->t("Total room price:"), $root),
                $this->getField($this->t("Total room price (incl. tax):"), $root),
                $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Total room price (incl. tax):") . "')][1]", $root, "#:\s(.+)#")
            ));

            $currency = currency(orval(
                $this->getField($this->t("Total room price:"), $root),
                $this->getField($this->t("Total room price (incl. tax):"), $root),
                $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Total room price (incl. tax):") . "')][1]", $root, "#:\s(.+)#")
            ));

            $h->price()
                ->total($total)
                ->currency($currency);

            if ($resDate = strtotime($this->normalizeDate($this->part(" | ", $this->getField($this->t("Reservation date:")), 0)))) {
                $h->general()
                    ->date($resDate);
            }
        }
    }

    private function normalizeDate($str)
    {
        switch ($this->lang) {
            default:
                $in = "#^[^\d\s]+\.?\s+(\d+).(\d+).(\d{4})$#";
                $this->findDay();

                switch ($this->findDay()) {
                    case 1:
                        $out = "$1.$2.$3";

                    break;

                    case 2:
                        $out = "$2.$1.$3";

                    break;

                    default:
                        $out = "$1.$2.$3";

                    break;
                }

            break;
        }

        return preg_replace($in, $out, $str);
    }

    private function getField($field, $root = null, $force = false)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));

        if ($force) {
            return $this->http->FindSingleNode("(.//td[{$rule}])[1]/following-sibling::td[last()]", $root);
        }

        return $this->http->FindSingleNode(".//td[{$rule}]/following-sibling::td[last()]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function part($exp, $str, $part)
    {
        $parts = explode($exp, $str);

        if (isset($parts[$part])) {
            return $parts[$part];
        }

        return null;
    }

    private function next($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));

        return $this->http->FindSingleNode(".//text()[{$rule}]/following::text()[normalize-space(.)][not(contains(normalize-space(), 'stars'))][{$n}]", $root);
    }

    private function findDay()
    {
        $str = $this->getField($this->t("Arrival / Departure:"), null, true);
        preg_match("#(\d+).(\d+).(\d+)#", $this->part(" - ", $str, 0), $start);
        preg_match("#(\d+).(\d+).(\d+)#", $this->part(" - ", $str, 1), $end);
        $res = [];

        for ($i = 1; $i <= 3; $i++) {
            if (isset($start[$i], $end[$i])) {
                $res[abs($start[$i] - $end[$i])] = $i;
            }
        }
        ksort($res);

        return end($res);
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
}
