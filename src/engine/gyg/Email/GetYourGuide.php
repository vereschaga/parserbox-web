<?php

namespace AwardWallet\Engine\gyg\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class GetYourGuide extends \TAccountChecker
{
    public $mailFiles = "gyg/it-104541048.eml, gyg/it-144134945.eml, gyg/it-153234766.eml, gyg/it-162319538.eml, gyg/it-49648447.eml, gyg/it-50863545.eml, gyg/it-53374303.eml, gyg/it-549781916.eml, gyg/it-55697271.eml, gyg/it-55741764.eml, gyg/it-61784986.eml, gyg/it-666039224.eml, gyg/it-666080228.eml, gyg/it-667235126.eml";
    public $From = 'GetYourGuide';
    public $headers;

    public $Subject = [
        "en" => "Your booking is confirmed",
        "You successfully canceled your booking - ",
        "pt" => "A sua reserva foi confirmada",
        "fr" => "Votre réservation est confirmée",
        "Votre réservation a été annulée",
        "de" => "Ihre Buchung ist bestätigt",
        "es" => "Se ha confirmado tu reserva",
        "it" => "La tua prenotazione è confermata",
        "La tua prenotazione è stata cancellata - ",
        "sv" => "Din bokning är bekräftad",
    ];

    public $reBody = [
        "en"  => ["Booking reference", "Booking Number"],
        "pt"  => "Código da reserva",
        "fr"  => "Référence de la réservation",
        "de"  => "Buchungsreferenz",
        "es"  => "Código de reserva",
        "it"  => "Codice di prenotazione",
        "pl"  => "Dziękujemy za rezerwację",
        "sv"  => "Här är detaljerna för din resa",
    ];

    public static $dictionary = [
        "en" => [ // it-49648447.eml, it-55697271.eml
            "Booking reference" => ["Booking Number", "Booking reference"],
            "Location"          => ["Location", "Meeting point address", "Your meeting point"],
            "Guests"            => ["Adults", "Adult", "Kids", "Seniors", "Children", "Youth", 'Student'],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            ", your tickets are on their way"            => [", your tickets are on their way", ', your booking is reserved!', ', Here\'s 1 of Your Tickets.'],
            //            "Duration"            => "",
            "hours"            => ["hours", "hour"],
            "days"             => ["days", "day"],
            "cancelled Text"   => ["You successfully canceled your booking", "Booking has been cancelled", 'we’re sorry you had to cancel.'],
        ],
        "pt" => [ // it-50863545.eml, it-55741764.eml
            "Booking reference"                          => ["Código da reserva"],
            "Guests"                                     => ["Adultos", "Adulto", "Crianças"],
            "Manage your booking"                        => ["Gerenciar reserva"],
            "Thanks for your order,"                     => ["Obrigado por reservar conosco, ", 'Comece a contagem regressiva, ', 'Obrigado por sua reserva, '],
            ", your tickets are on their way"            => [", confira aqui "],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            "Questions? Contact the local partner" => ["Dúvidas? Entre em contato com o parceiro local"],
            "Location"                             => ["Você forneceu o seguinte endereço:", "Endereço do ponto de encontro", "Local da Partida"],
            "Total price:"                         => ["Preço total:"],
            "Your booking is "                     => ["A sua reserva foi "],
            "Time"                                 => ["Horário"],
            "Duration"                             => "Duração",
            "hours"                                => ["horas", "hora"],
            "days"                                 => ["dia", "dias"],
            "minutes"                              => ["minutos"],
            "More details"                         => "Mais detalhes",
            // "cancelled Text" => [""],
        ],
        "fr" => [ // it-53374303.eml
            "Booking reference"               => ["Référence de la réservation"],
            "Guests"                          => ["Adulte", "Enfant", 'Enfants', 'Adultes'],
            "Manage your booking"             => ["Gérer votre réservation"],
            "Thanks for your order,"          => ["Merci pour votre réservation,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            ", your tickets are on their way" => [", nous sommes désolés que vous ayez eu à annuler votre réservation."],
            //"Questions? Contact the local partner" => [""],
            "Location"         => ["Adresse du point de rencontre", "Adresse du lieu de rendez-vous"],
            "Total price:"     => ["Prix total :", "Prix total:"],
            "Your booking is " => ["Votre réservation est "],
            //"Time" => [""],
            "Duration"            => "Durée :",
            "hours"               => ["heures", "heure"],
            //            "days"            => ["days", "day"],
            //            "minutes"            => [""],
            "cancelled Text" => ["Votre réservation a été annulée", "La réservation a été annulée"],
        ],
        "de" => [ // it-61784986.eml
            "Booking reference"      => ["Buchungsreferenz"],
            "Guests"                 => ["Erwachsene", "Kinder"],
            "Manage your booking"    => ["Buchung verwalten"],
            "Thanks for your order," => ["Danke für Ihre Bestellung,", "Danke für deine Bestellung,", "Danke für deine Buchung,", "Vielen Dank für deine Buchung,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            //"Questions? Contact the local partner" => [""],
            "Location"         => ["Adresse des Treffpunktes", "Ort"],
            "Total price:"     => ["Gesamtpreis:"],
            "Your booking is " => ["Ihre Buchung ist bestätigt "],
            //"Time" => [""],
            "Duration"            => "Dauer",
            "hours"               => ["Stunden", 'Stunde'],
            //            "days"            => ["days", "day"],
            //            "minutes"            => [""],
            // "cancelled Text" => [""],
        ],
        "es" => [ // it-104541048.eml
            "Booking reference"      => ["Código de reserva"],
            "Guests"                 => ["Adulto", 'Adultos', 'Niños'],
            "Manage your booking"    => ["Gestiona tu reserva"],
            "Thanks for your order," => ["Gracias por tu reserva,", "Gracias por tu pedido,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            //"Questions? Contact the local partner" => [""],
            "Location"     => ["Dirección del punto de encuentro", "Lugar"],
            "Total price:" => ["Precio total:"],
            //"Your booking is " => [""],
            //"Time" => [""],
            "Duration"            => "Duración",
            "hours"               => ["horas", "hora"],
            //            "days"            => ["days", "day"],
            //            "minutes"            => [""],
            // "cancelled Text" => [""],
        ],
        "it" => [ // it-153234766.eml
            "Booking reference" => ["Codice di prenotazione"],
            "cancelled Text"    => "La prenotazione è stata annullata",
            "Guests"            => ["Adulto", 'Adulti', 'Bambini'],
            //            "Manage your booking"    => [""],
            "Thanks for your order," => ["Grazie per la tua prenotazione,", "Ci dispiace che tu abbia cancellato la prenotazione,", "Grazie per aver prenotato,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            //"Questions? Contact the local partner" => [""],
            "Location"         => ["Indirizzo del punto d'incontro", "Indirizzo"],
            "Total price:"     => ["Prezzo complessivo:"],
            "Your booking is " => ["La tua prenotazione è"],
            //"Time" => [""],
            "Duration"            => "Durata",
            "hours"               => ["ora", "ore"],
            //            "days"            => ["days", "day"],
            "minutes"            => ["minuti"],
            // "cancelled Text" => [""],
        ],
        "pl" => [ // it-144134945.eml
            "Booking reference" => ["Numer rezerwacji"],
            //"cancelled Text" => "",
            "Guests"            => ['Dorośli', 'Dzieci'],
            //            "Manage your booking"    => [""],
            "Thanks for your order," => ["Dziękujemy za rezerwację,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            //"Questions? Contact the local partner" => [""],
            "Location"         => ["Adres miejsca zbiórki"],
            "Total price:"     => ["Więcej informacji"],
            "Your booking is " => ["Twoja rezerwacja jest"],
            //"Time" => [""],
            "Duration"            => "Czas trwania",
            "hours"               => ["godz."],
            //            "days"            => ["days", "day"],
            "minutes"            => ["minuti"],
            // "cancelled Text" => [""],
        ],
        "sv" => [ // it-162319538.eml
            "Booking reference" => ["Bokningsnummer"],
            //"cancelled Text" => "",
            "Guests"                 => ['Vuxna'],
            "Manage your booking"    => ["Mer information"],
            "Thanks for your order," => ["Tack för din bokning,"],
            //            'Your activity has been updated' = > '', // text when there is no traveler name
            //"Questions? Contact the local partner" => [""],
            "Location"         => ["Mötesplatsadress"],
            "Total price:"     => ["Totalt pris:"],
            //"Your booking is " => [""],
            //"Time" => [""],
            //"Duration"            => "",
            //"hours"               => [""],
            //            "days"            => [""],
            //"minutes"            => [""],
            // "cancelled Text" => [""],
        ],
    ];

    public $lang;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->headers = $parser->getHeaders();

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Location']) && $this->http->XPath->query("//text()[{$this->contains($dict['Location'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            } elseif (!empty($dict['Booking reference']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking reference'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if ($this->http->XPath->query("//tr[{$this->starts($this->t('Booking reference'))}]")->length > 0) {
            $this->ParseEvent($email);
        } else {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->ParseEventPdf($email, $text);
            }
        }

        $link = $this->http->FindSingleNode("//a[{$this->eq($this->t('Print voucher'))}]/@href[contains(., '.getyourguide.com') or contains(., '.sendgrid.net')]");

        if (count($email->getItineraries()) === 0 && !empty($link)) {
            $this->http->GetURL($link);

            if (($text = \PDF::convertToText($this->http->Response['body'])) !== null) {
                $this->ParseEventPdf($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $segments = $this->http->XPath->query("//tr[{$this->starts($this->t('Booking reference'))}]");

        foreach ($segments as $root) {
            $isCancelled = false;

            if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelled Text")) . "])[1]"))) {
                $isCancelled = true;
            }

            if ($segments->length > 1) {
                $address = $this->http->FindSingleNode("(./ancestor::table[2]/following-sibling::table[{$this->contains($this->t('Location'))}])[1]/descendant::tr[{$this->starts($this->t('Location'))}][1]/following-sibling::tr[not({$this->starts($this->t('Location'))})][normalize-space()][1]", $root);
            } else {
                $address = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Location'))}][not(following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Location'))}]) and not(preceding-sibling::tr[normalize-space()][2][{$this->starts($this->t('Location'))}])]/following::tr[normalize-space()][1][not(contains(normalize-space(), 'When'))]");
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Get our free app'))}]/following::tr[{$this->starts($this->t('Meeting point address'))}][1]/following::tr[normalize-space()][1]");
            }

            if (!empty($address) || $isCancelled == true) {
                $event = $email->add()->event();

                $event->setEventType(Event::TYPE_EVENT);

                $status = $this->re("/{$this->opt($this->t('Your booking is '))}(.+)\s*[-]/u", $this->headers['subject']);

                if (!empty($status)) {
                    $event->general()
                        ->status($status);
                }

                if ($isCancelled) {
                    $event->general()
                        ->cancelled()
                        ->status('Cancelled');
                }

                $phone = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Questions? Contact the local partner'))}]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]");

                if (!empty($phone)) {
                    $event->place()
                        ->phone($phone);
                }

                $confDescription = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/descendant::text()[normalize-space()][1]", $root);

                $event->general()
                    ->confirmation($this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/descendant::text()[normalize-space()][2]", $root, true, '/([A-Z\d+]{11,})/'), $confDescription)
                ;

                $traveller = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Booking reference'))}]/preceding::text()[{$this->starts($this->t('Thanks for your order,'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Thanks for your order,'))}(.+)[.!]\s*$/");

                if (empty($traveller)) {
                    $traveller = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Booking reference'))}]/preceding::text()[{$this->contains($this->t(', your tickets are on their way'))}][1]", null, true, "/^(.+){$this->opt($this->t(', your tickets are on their way'))}/");
                }

                if (empty($traveller)) {
                    $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thanks for your order,'))}]", null, true, "/{$this->opt($this->t('Thanks for your order,'))}\s*(\w+)(?:\!|\.)/");
                }

                if (empty($traveller) && !empty($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your activity has been updated'))}])"))) {
                } else {
                    $event->general()
                        ->traveller($traveller, false);
                }

                $event->place()
                    ->name($this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}][1]/preceding-sibling::tr[normalize-space()][2]", $root))
                ;

                if ($isCancelled === true) {
                } else {
                    $event->place()
                        ->address($address);
                }

                $guests = implode(" ", $this->http->FindNodes("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/following-sibling::tr[normalize-space()][2]//text()[normalize-space()]", $root));

                if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('Guests'))}/", $guests, $guestsMatches)) {
                    $event->booked()->guests(array_sum($guestsMatches[1]));
                }

                $startDateText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/following-sibling::tr[1]/descendant::text()[normalize-space()][1]", $root);
                $event->booked()
                    ->start($this->normalizeDate($startDateText))
                ;

                $endDate = null;

                if (!empty($event->getStartDate()) && preg_match("/\b\d{1,2}:\d{2}\b/", $startDateText)) {
                    $duration = '';
                    $durationText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/following-sibling::tr[1]/descendant::text()[" . $this->contains($this->t("Duration")) . "]",
                        $root, true, "/\|\s*" . $this->opt($this->t("Duration")) . "\s*(.+)/");

                    if (preg_match("/^\s*(\d+)\s*" . $this->opt($this->t("hours")) . "\s*$/u", $durationText, $m)) {
                        $duration = $m[1] . ' hours';
                    } elseif (preg_match("/^\s*(\d+[,.]\d{1,2})\s*" . $this->opt($this->t("hours")) . "\s*$/u", $durationText, $m)) {
                        $duration = (int) (str_replace(',', '.', $m[1]) * 60.0) . ' minutes';
                    } elseif (preg_match("/^\s*(\d+)\s*" . $this->opt($this->t("days")) . "\s*$/u", $durationText, $m)) {
                        $duration = $m[1] . ' days';
                    } elseif (preg_match("/^\s*(\d+)\s*" . $this->opt($this->t("minutes")) . "\s*$/u", $durationText, $m)) {
                        $duration = $m[1] . ' minutes';
                    }

                    if (!empty($duration)) {
                        $duration = '+ ' . $duration;
                        $endDate = strtotime($duration, $event->getStartDate());
                    }
                }

                if (!empty($endDate)) {
                    $event->booked()
                        ->end($endDate);
                } else {
                    $event->booked()
                        ->noEnd();
                }

                $totalText = null;

                if ($segments->length > 1) {
                    $totalText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::td[{$this->starts($this->t('Add to calendar'))}]/following::td[normalize-space()][1]", $root);

                    if (empty($totalText)) {
                        $totalText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::tr[{$this->starts($this->t('Booking reference'))}]/following-sibling::tr[normalize-space()][4]", $root);
                    }

                    if (empty($totalText)) {
                        $totalText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->starts($this->t('More details'))}]/following::text()[normalize-space()][1]", $root);
                    }
                }

                if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalText, $matches)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalText, $matches)
                ) {
                    $currency = $this->normalizeCurrency($matches['curr']);
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                    $event->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
                }

                $cancellation = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Manage your booking'))}]/following::tr[normalize-space()][2]");

                if (!empty($cancellation)) {
                    $event->setCancellation($cancellation);
                }
            }
        }

        $totalText = $this->http->FindSingleNode("./following::tr[{$this->starts($this->t('Total price:'))}][1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()][last()]", $root);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalText, $matches)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalText, $matches)
        ) {
            $currency = $this->normalizeCurrency($matches['curr']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

            if ($segments->length > 1) {
                $email->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
            } elseif (isset($event)) {
                $event->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
            }
        }
    }

    public function ParseEventPdf(Email $email, string $text)
    {
        $e = $email->add()->event();

        $e->type()->event();

        $conf = $this->re("/\n.* {5,}Booking reference: *([A-Z\d]+)\n/", $text);
        $e->general()
            ->confirmation($conf)
            ->traveller($this->re("/Booked by\n\s*((?:\S ?)+?) *(?:\(| {5,}|\n)/", $text), true);

        if (!empty($conf) && preg_match("/^\s*(?:.*\n+){0,3} *{$conf}\b.*\n+( {0,20}\S.+(?:\n+.*){0,10}?)\n *Option ?\(/u", $text, $m)
            && preg_match_all("/^ {0,20}(\S.+?)( {5,}.*| *$)/m", $m[1], $mat)
        ) {
            $e->place()
                ->name(implode(' ', $mat[1]));
        } elseif (!empty($conf) && preg_match("/Page\s+\d+\s*of\s*\d+\n+\s*{$conf}\n+[ ]{10,}(?<name>.+\:.+)\n+[\s\n]+[A-Z\d\-]{20,}\n/", $text, $m)) {
            $e->place()
                ->name($m[1]);
        }

        if (preg_match("/Booked by\n\s*[^\n\d]+\(\d+\/(\d+)\)/", $text, $m)
            || preg_match("/\s+.+[AP]M {3,}(\d+)\s*Adults/", $text, $m)
        ) {
            $e->booked()
            ->guests($m[1]);
        }

        $address = $this->re("/\n {0,5}Meeting point address\n\s*((?:.+\n){1,5}?) {0,5}(?:Address\n|Please show your GetYourGuide)/", $text);

        if (empty($address)) {
            $address = $this->re("/\n {0,10}(?:Meeting point address|Meeting point)\n+\s*((?:.+\n){1,5}?)\n+ {0,10}(?:Address\n|Please show your GetYourGuide|You will meet your guide in)/", $text);
        }

        $e->setAddress(preg_replace("/\s+/", " ", $address));

        if (preg_match("/\n {0,5}Meeting point address\n\s*(?:.+\n){1,5}? {0,5}(Address)\n((?:.+\n){1,8}?) {0,5}(When to arrive)\n((?:.+\n){1,8}?)\n {0,5}End location\n/", $text, $m)) {
            $notes = $m;
            unset($notes[0]);
            $notes = preg_replace("/\s+See previous page for larger map\s+/", " ", $notes);
            $notes = preg_replace("/\s+/", " ", $notes);
            $notes = preg_replace("/([^.\s])\s*$/", "$1:", $notes);

            if (!empty($notes)) {
                $e->general()
                    ->notes(implode(" ", $notes));
            }
        }

        $e->setStartDate($this->normalizeDate($this->re("/\s+(.+[AP]M) {3,}\d*\s*Adults/", $text)));

        $duration = $this->re("/Duration\s*(\d.*)[ ]{5,}/", $text);

        if (preg_match('/^\s*(\d+\.\d) hours?/', $duration, $m)) {
            // 1.5 hours -> 90 minute
            // 2.5 hours -> 150 minute
            $duration = (int) ((float) $m[1] * 60.0) . ' minute';
        }

        if (!empty($duration)) {
            $e->setEndDate(strtotime($duration, $e->getStartDate()));
        } else {
            $e->setNoEndDate(true);
        }

        if (preg_match("/\n {0,10}{$this->opt($this->t('Cancellation policy'))}\n(\S.+)\n{3,}/", $text, $m)) {
            $e->general()
                ->cancellation($m[1]);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@getyourguide.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->Subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'This is your GetYourGuide voucher') !== false
                && stripos($text, 'Meeting point address') !== false
                && stripos($text, 'Booked by') !== false) {
                return true;
            }
        }

        if ($this->http->XPath->query("//a[contains(@href, '.getyourguide.')]")->length === 0
            && $this->http->XPath->query("//text()[{$this->contains(['The GetYourGuide Team'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->reBody as $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0
            ) {
                return true;
            }
        }

        $link = $this->http->FindSingleNode("//a[{$this->eq($this->t('Print voucher'))}]/@href[contains(., '.getyourguide.com') or contains(., '.sendgrid.net')]");

        if (!empty($link)) {
            return true;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //January 8, 2020 5:50 AM | Duration 6 hours
            "#^(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*([\d\:]+\s(?:AM|PM))\s[|].+$#",
            //2 de janeiro de 2020 às 06:00 | - lang pt
            "#^(\d{1,2})\s*de\s*(\w+)\s*de\s*(\d{4})\s*(?:às)?\s*([\d:]+)\s[|].+$#u",
            //13 January 2020 6:00 AM | Duration 12 hours
            "#^(\d{1,2})[.]?\s*(\w+)\s*(\d{4})\s*([\d:]+\s*(?:AM|PM)?)\s*[|].+$#iu",
            //2 de janeiro de 2020 | - lang pt
            "#^(\d{1,2})\s*de\s*(\w+)\s*de\s*(\d{4})\s[|].+$#u",
            //May 17, 2021 | Valid 1 day
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*[\|].*$#u",
            //10 agosto 2021 |
            // 10. Juni 2022 | Gültigkeit 1 Tag
            "#^\s*(\d+)[.]?\s*(\w+)\s*(\d{4})\s*[\|]+.*$#u",
            //Thu, November 23, 2023 at 4:00 PM
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3",
            "$2 $1 $3",
            "$1 $2 $3",
            "$2 $1 $3, $4",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#[[:alpha:]]{3,}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[0], $this->lang)) {
                $str = str_replace($m[0], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
            'JPY' => ['¥'],
            'PLN' => ['zł'],
            'THB' => ['฿'],
            'CAD' => ['C$'],
            'COP' => ['COL$'],
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
