<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationFlight extends \TAccountChecker
{
    public $mailFiles = "iberia/it-147127888.eml, iberia/it-149564132.eml, iberia/it-155584282.eml, iberia/it-324555844.eml";
    public $subjects = [
        // en
        '/^Confirmation of booking\s*[A-Z\d]+\s*to\s*\D+$/',
        // nl
        '/^Bevestiging van reservering\s*[A-Z\d]+\s*naar\s*\D+$/',
        // ru
        '/^подтверждение бронирования\s*[A-Z\d]+\s*в\s*\D+$/',
        // de
        '/^Bestätigung der Buchung\s*[A-Z\d]+\s*nach\s*\D+$/',
        // fr
        '/^Confirmation de la réservation\s*[A-Z\d]+\s*à\s*\D+$/',
        // it
        '/^Conferma della prenotazione\s*[A-Z\d]+\s*a\s*\D+$/',
        // pt
        '/^Confirmação da reserva\s*[A-Z\d]+ para \D+$/',
        // es
        '/^Confirmación de reserva\s*[A-Z\d]+ a \D+$/',
    ];

    public $detectLang = [
        "pt" => ["Detalhes da reserva", 'Reserva confirmada'],
        "es" => ["Detalles de la reserva"],
        "en" => ["Booking details", "Booking confirmed"],
        "nl" => ["Details van de reservering"],
        "ru" => ["Детали бронирования"],
        "de" => ["Buchungsdetails"],
        "fr" => ["Détails de la réservation", 'Réservation confirmée'],
        "it" => ["Dettaglio della prenotazione"],
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Thank you for choosing Iberia. The details of your booking are shown below',
            'Purchase confirmed'    => ['Purchase confirmed', 'Booking confirmed'],
            'Booking reference:'    => 'Booking reference:',
            'Passenger information' => 'Passenger information',
        ],
        "es" => [
            'Purchase confirmed'    => ['Compra confirmada', 'Reserva confirmada'],
            'Booking reference:'    => 'Código de reserva:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Gracias por confiar en Iberia, a continuación tienes los detalles de tu reserva',
            'Passenger information' => 'Información de pasajeros',
            'Passenger'             => 'Pasajero',
            'Loyalty card'          => 'Tarjeta de fidelización',
            'Departure'             => 'Salida',
            'Arrival'               => 'Llegada',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'CABINA',
            'SEAT'                  => 'ASIENTO',
            'Purchase summary'      => 'Resumen de compra',
            'Flights'               => 'Vuelos',
            'Taxes'                 => 'Tasas',
            'Payment details'       => 'Detalles del pago',
            'Total Price'           => 'Precio Total',
        ],
        "pt" => [
            'Purchase confirmed'    => ['Compra confirmada', 'Reserva confirmada'],
            'Booking reference:'    => 'Código de reserva:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Obrigado pela confiança na Iberia, você verá os detalhes da sua reserva abaixo',
            'Passenger information' => 'Dados dos passageiros',
            'Loyalty card'          => 'Cartão do programa de fidelidade',
            'Passenger'             => 'Passageiro',
            'Departure'             => 'Partida',
            'Arrival'               => 'Chegada',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'CABINE',
            'SEAT'                  => 'ASSENTO',
            'Purchase summary'      => 'Resumo da compra',
            'Flights'               => 'Voos',
            'Taxes'                 => 'Taxas',
            'Payment details'       => 'Detalhes do pagamento',
            'Total Price'           => 'Preço total',
        ],
        "nl" => [
            'Purchase confirmed'    => 'Aankoop bevestigd',
            'Booking reference:'    => 'Reserveringsnummer:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Bedankt voor het vertrouwen in Iberia, hieronder staan de details van je reservering',
            'Passenger information' => 'Passagiersinformatie',
            'Passenger'             => 'Passagier',
            'Loyalty card'          => 'Klantenkaart',
            'Departure'             => 'Vertrek',
            'Arrival'               => 'Aankomst',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'CABINE',
            'SEAT'                  => 'STOEL',
            'Purchase summary'      => 'Overzicht van aankoop',
            'Flights'               => 'Vluchten',
            'Taxes'                 => 'Toeslagen',
            'Payment details'       => 'Betaaldetails',
            'Total Price'           => 'Totaalprijs',
        ],
        "ru" => [
            'Purchase confirmed'    => 'Покупка подтверждена',
            'Booking reference:'    => 'Код бронирования:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Благодарим вас за доверие к Iberia. Ниже вы найдете подробную информацию о бронировании',
            'Passenger information' => 'Данные пассажиров',
            'Passenger'             => 'Пассажир',
            'Loyalty card'          => 'Карта программы для постоянных клиентов',
            'Departure'             => 'Вылет',
            'Arrival'               => 'Прибытие',
            'Terminal'              => 'Терминал',
            'CABIN'                 => 'САЛОН',
            'SEAT'                  => 'МЕСТО',
            'Purchase summary'      => 'Итог покупки',
            'Flights'               => 'Рейсы',
            'Taxes'                 => 'Тариф',
            'Payment details'       => 'Сведения об оплате',
            'Total Price'           => 'Общая стоимость',
        ],
        "de" => [
            'Purchase confirmed'    => 'Buchung bestätigt',
            'Booking reference:'    => 'Buchungscode:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Vielen Dank für Ihr Vertrauen zu Iberia. Nachfolgend finden Sie die Einzelheiten Ihrer Buchung',
            'Passenger information' => 'Passagierdaten',
            'Passenger'             => 'Passagier',
            'Loyalty card'          => 'Karte für Vielflieger',
            'Departure'             => 'Abflug',
            'Arrival'               => 'Ankunft',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'KABINE',
            'SEAT'                  => 'SITZPLATZ',
            'Purchase summary'      => 'Zusammenfassung der Buchung',
            'Flights'               => 'Flüge',
            'Taxes'                 => 'Gebühren',
            'Payment details'       => 'Zahlungsdetails',
            'Total Price'           => 'Gesamtpreis',
        ],
        "fr" => [
            'Purchase confirmed'    => ['Achat confirmé', 'Réservation confirmée'],
            'Booking reference:'    => 'Code de réservation:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Nous vous remercions de la confiance que vous accordez à Iberia, vous trouverez ci-dessous les détails de votre réservation',
            'Passenger information' => 'Informations sur les passagers',
            'Passenger'             => 'Passager',
            'Loyalty card'          => 'Carte de fidélité',
            'Departure'             => 'Sortie',
            'Arrival'               => 'Arrivée',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'CABINE',
            'SEAT'                  => 'SIÈGE',
            'Purchase summary'      => 'Récapitulatif de l\'achat',
            'Flights'               => 'Vols',
            'Taxes'                 => 'Taxes',
            'Payment details'       => 'Détails du paiement',
            'Total Price'           => 'Prix total',
        ],
        "it" => [
            'Purchase confirmed'    => 'Acquisto confermato',
            'Booking reference:'    => 'Codice di prenotazione:',
            'Thank you for choosing Iberia. The details of your booking are shown below'
                                    => 'Grazie per la fiducia riposta in Iberia. Di seguito troverai il dettaglio della tua prenotazione',
            'Passenger information' => 'Informazioni dei passeggeri',
            'Passenger'             => 'Passeggero',
            'Loyalty card'          => 'Carta fedeltà',
            'Departure'             => 'Partenza',
            'Arrival'               => 'Arrivo',
            'Terminal'              => 'Terminal',
            'CABIN'                 => 'CABINA',
            'SEAT'                  => 'POSTO',
            'Purchase summary'      => 'Riepilogo dell\'acquisto',
            'Flights'               => 'Voli',
            'Taxes'                 => 'Tasse',
            'Payment details'       => 'Dettagli del pagamento',
            'Total Price'           => 'Prezzo totale',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@experienciaiberia.iberia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing Iberia. The details of your booking are shown below'))}]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('Purchase confirmed'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking reference:'))}]")->length > 0)

                || ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking details'))}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Outward'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]experienciaiberia\.iberia\.com\D*$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $travelers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger information'))}]/following::text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr", null, "/^(\D+)\s+\(/");

        if (count($travelers) == 0) {
            $travelers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger information'))}]/following::text()[{$this->eq($this->t('Passenger'))}]/ancestor::table[2]/following-sibling::table/descendant::text()[contains(normalize-space(), '(')]", null, "/^(\D+)\s+\(/");
        }

        if (count($travelers) == 0) {
            $travelers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger information'))}]/following::text()[{$this->eq($this->t('Passenger'))}]/ancestor::table[2]/following::text()[normalize-space()='Add']/ancestor::table[2]/descendant::text()[contains(normalize-space(), '(')]", null, "/^(\D+)\s+\(/");
        }

        $f->general()
           ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking reference:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]{4,})/"))
           ->travellers(array_filter(array_unique($travelers)), true);

        $accountsXpath = "//text()[{$this->contains($this->t('Passenger information'))}]/following::text()[{$this->eq($this->t('Loyalty card'))}]/ancestor::tr[1][following-sibling::tr]";
        $accountNodes = $this->http->XPath->query($accountsXpath);

        foreach ($accountNodes as $aRoot) {
            $number = $this->http->FindSingleNode("following-sibling::tr", $aRoot, true, "/^([A-z\d]{4,})$/");

            if (!empty($number)) {
                $name = $this->http->FindSingleNode("preceding::td[not(.//td)][1]", $aRoot, true, "/^\s*(\D{3,}?)\s*\(.+/");
                $f->program()
                    ->account($number, false, $name);
            }
        }

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Departure'))}]/ancestor::table[2]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
               ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/^([A-Z\d]{2})/"))
               ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::td[1]", $root, true, "/^[A-Z\d]{2}(\d{2,4})/"));

            $depPointText = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::td[1]", $root);
            $arrPointText = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival'))}]/ancestor::td[1]", $root);

            if (preg_match("/^{$this->opt($this->t('Departure'))}\s*\D*(?<depCode>[A-Z]{3})\s*(?<depName>[A-Z]\D+)\s*{$this->opt($this->t('Terminal'))}\s*(?<depTerminal>.+)$/", $depPointText, $m)
             || preg_match("/^{$this->opt($this->t('Departure'))}\s*\D*(?<depCode>[A-Z]{3})\s*(?<depName>[A-Z]\D+)$/", $depPointText, $m)) {
                $s->departure()
                   ->code($m['depCode'])
                   ->name($m['depName']);

                if (isset($m['depTerminal'])) {
                    $s->departure()
                    ->terminal($m['depTerminal']);
                }
            }

            $s->departure()
               ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::td[1]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]/descendant::td[1]", $root)));

            if (preg_match("/^{$this->opt($this->t('Arrival'))}\s*\D*(?<arrCode>[A-Z]{3})\s*(?<arrName>[A-Z]\D+)\s*{$this->opt($this->t('Terminal'))}\s*(?<arrTerminal>.+)$/", $arrPointText, $m)
            || preg_match("/^{$this->opt($this->t('Arrival'))}\s*\D*(?<arrCode>[A-Z]{3})\s*(?<arrName>[A-Z]\D+)$/", $arrPointText, $m)) {
                $s->arrival()
                   ->code($m['arrCode'])
                   ->name($m['arrName']);

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                       ->terminal($m['arrTerminal']);
                }
            }

            $s->arrival()
               ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure'))}]/ancestor::td[1]/ancestor::tr[1]/following::tr[contains(normalize-space(), ':')][1]/descendant::td[last()]", $root)));

            $seats = array_filter(explode(',', $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('SEAT'))}]/following::text()[normalize-space()][1]", $root, true, "/.*\d{1,3}[A-Z].*/")));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('CABIN'))}]/following::text()[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }

        $total = array_filter(array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Payment details'))}]/following::text()[{$this->contains($this->t('Total Price'))}][1]/following::text()[normalize-space()][1]", null, "/^\s*([\d\,\.]+)/u")));

        if (count($total) == 1) {
            $currency = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Payment details'))}]/following::text()[{$this->contains($this->t('Total Price'))}])[1]/following::text()[normalize-space()][1]", null, true, "/^\s*[\d\,\.]+\s*(\S+)/u");
            $total = $total[0];
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Price'))}][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*([\d\,\.]+)/u");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Price'))}][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*([\d\,\.]+)/u");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment details'))}]/following::text()[{$this->contains($this->t('Total Price'))}][1]/following::text()[normalize-space()][1]", null, true, "/^\s*[\d\,\.]+\s*(\S+)/u");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Price'))}][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*[\d\,\.]+\s*(\S+)/u");
        }

        if (empty($total) && empty($currency)) {
            $price = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Payment details'))}])[1]/following::text()[{$this->contains($this->t('Total Price'))}][1]/following::text()[normalize-space()][1]");

            if (preg_match("/^\s*(\D+?)\s*([\d\.\,]+)/", $price, $m)) {
                $total = $m[2];
                $currency = $m[1];
            }
        }

        if (!empty($total) && !empty($currency) && !empty($currency = $this->normalizeCurrency($currency))) {
            if (preg_match("/^\s*\d[,\d. ]*$/", $total)) {
                $f->price()
                    ->total(PriceHelper::parse($total, $currency))
                    ->currency($currency);
            }
        }

        $priceXpath = "//text()[{$this->eq($this->t('Purchase summary'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Flights'))}]"
            . "/ancestor::*[{$this->starts($this->t('Flights'))}][count(.//td[normalize-space()][not(.//td)]) > 3][position() < 5][following-sibling::*[normalize-space()]][1]/following-sibling::*[normalize-space()]";
        $cost = 0.0;
        $tax = 0.0;
        $priceNodes = $this->http->XPath->query($priceXpath);

        foreach ($priceNodes as $pRoot) {
            $values = $this->http->FindNodes("descendant::td[normalize-space()][not(.//td)]", $pRoot);
            $count = $this->re("/^\s*(\d+) [[:alpha:]]+\s*$/u", $values[0] ?? '');

            if (empty($count)) {
                break;
            }

            if (count($values) > 3) {
                $cost += $count * PriceHelper::parse($this->re("/^\s*\D{0,5}(\d[\d\,\.]+)\D{0,5}$/u", $values[1]), $currency);

                for ($i = 2; $i <= 2 + count($values) - 4; $i++) {
                    if (preg_match("/^\s*\D{0,5}(\d[\d\,\.]+)\D{0,5}$/u", $values[$i], $m)) {
                        $tax += $count * PriceHelper::parse($m[1], $currency);
                    }
                }
            } else {
                $cost = null;
                $tax = null;

                break;
            }
        }

        if ($priceNodes->length > 0) {
            if (!empty($cost)) {
                $f->price()
                    ->cost($cost);
            }

            if (!empty($tax)) {
                $f->price()
                    ->tax($tax);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseEmail($email);

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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // 08:00 Sunday 3 April 2022
            "/^([\d\:]+)\s*[\w\-]+\s*(\d+)\s*(\w+)\s*(\d{4})$/iu",
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Passenger information'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Passenger information'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict['Booking details'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
