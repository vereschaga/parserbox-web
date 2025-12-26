<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "navan/it-433404168.eml, navan/it-636764401.eml";

    public $detectSubjects = [
        // en
        ' stay at ',
        'You canceled your ',
        // de
        'Ihr Aufenthalt am ',
        'Sie haben Ihren ',
        // fr
        ' séjour à ',
        'Vous avez annulé votre séjour ',
        // pl
        'Anulowano swój pobyt ',
        // sv
        ' vistelse på ',
        // pt
        'A sua estadia ',
        // es
        'Tu estancia del ',
        // nl
        'verblijf in',
        // it
        'Il tuo soggiorno ',
        'Hai annullato il tuo soggiorno ',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Your hotel reservation is' => 'Your hotel reservation is',
            'canceled'                  => ['canceled', 'cancelled'],
            'Confirmation:'             => 'Confirmation:',
            // 'Pending' => '', // in conf number
            'Navan booking ID:'            => 'Navan booking ID:',
            'Booking details'              => 'Booking details',
            'Room type:'                   => 'Room type:',
            'Check-in:'                    => 'Check-in:',
            'Check-out:'                   => 'Check-out:',
            'Cancellation policy'          => 'Cancellation policy',
            'Fully refundable until'       => ['Fully refundable until', 'Change or cancel for free until'],
            'Non-refundable after booking' => 'Non-refundable after booking',
            'Traveler details'             => 'Traveler details',
            'Number of rooms'              => 'Number of rooms',
            'Price per night'              => 'Price per night',
            'Subtotal'                     => 'Subtotal',
            'Total'                        => 'Total',
        ],
        'de' => [
            'Your hotel reservation is'    => 'Ihr Hotelreservierung wurde',
            'canceled'                     => 'storniert',
            'Confirmation:'                => 'Bestätigungsnummer:',
            'Pending'                      => 'Ausstehend', // in conf number
            'Navan booking ID:'            => 'Navan Buchungs-ID:',
            'Booking details'              => 'Buchungsdetails',
            'Room type:'                   => 'Zimmertyp:',
            'Check-in:'                    => 'Check-in:',
            'Check-out:'                   => 'Check-out:',
            'Cancellation policy'          => 'Stornierungsrichtlinie',
            'Fully refundable until'       => ['Kostenlose Änderung oder Stornierung bis zum'],
            'Non-refundable after booking' => 'Nach der Buchung nicht erstattungsfähig',
            'Traveler details'             => 'Details zum Reisenden',
            'Number of rooms'              => 'Anzahl der Zimmer',
            'Price per night'              => 'Preis pro Nacht',
            'Subtotal'                     => 'Zwischensumme',
            'Total'                        => 'Gesamt',
        ],
        'fr' => [
            'Your hotel reservation is'    => "Votre réservation d'hôtel est",
            'canceled'                     => 'annulé',
            'Confirmation:'                => 'N° de confirmation :',
            'Pending'                      => 'En attente', // in conf number
            'Navan booking ID:'            => 'ID de réservation Navan :',
            'Booking details'              => 'Détails de la réservation',
            'Room type:'                   => 'Type de chambre :',
            'Check-in:'                    => 'Enregistrement :',
            'Check-out:'                   => 'Départ :',
            'Cancellation policy'          => "Politique d'annulation",
            'Fully refundable until'       => "Modification ou annulation gratuite jusqu'au",
            'Non-refundable after booking' => 'Non remboursable après la réservation',
            'Traveler details'             => 'Détails du voyageur',
            'Number of rooms'              => 'Nombre de chambres',
            'Price per night'              => 'Prix par nuit',
            'Subtotal'                     => 'Sous-total',
            'Total'                        => 'Total',
        ],
        'pl' => [
            'Your hotel reservation is' => "Twój rezerwacja hotelu jest",
            'canceled'                  => 'odwołany',
            'Confirmation:'             => 'Potwierdzenie nr:',
            // 'Pending' => '', // in conf number
            'Navan booking ID:'      => 'Identyfikator rezerwacji Navan:',
            'Booking details'        => 'Szczegóły rezerwacji',
            'Room type:'             => 'Typ pokoju:',
            'Check-in:'              => 'Zameldowanie:',
            'Check-out:'             => 'Wymeldowanie:',
            'Cancellation policy'    => "Zasady anulowania",
            'Fully refundable until' => "Bezpłatna zmiana lub anulowanie do",
            // 'Non-refundable after booking' => '',
            'Traveler details' => 'Dane podróżnego',
            'Number of rooms'  => 'Liczba pokoi',
            'Price per night'  => 'Cena za noc',
            'Subtotal'         => 'Suma cząstkowa',
            'Total'            => 'Ogółem',
        ],
        'sv' => [
            'Your hotel reservation is' => "Din hotellreservation är",
            // 'canceled' => 'odwołany',
            'Confirmation:' => 'Bekräftelse #:',
            // 'Pending' => '', // in conf number
            'Navan booking ID:'      => 'Navan-boknings-ID:',
            'Booking details'        => 'Bokningsuppgifter',
            'Room type:'             => 'Rumstyp:',
            'Check-in:'              => 'Incheckning:',
            'Check-out:'             => 'Utcheckning:',
            'Cancellation policy'    => "Avbokningspolicy",
            'Fully refundable until' => "Gratis ändring eller avbokning fram till",
            // 'Non-refundable after booking' => '',
            'Traveler details' => 'Resenärsuppgifter',
            'Number of rooms'  => 'Antal rum',
            'Price per night'  => 'Pris per natt',
            'Subtotal'         => 'Delsumma',
            'Total'            => 'Totalsumma',
        ],
        'pt' => [
            'Your hotel reservation is' => "O seu hotel está",
            // 'canceled' => 'odwołany',
            'Confirmation:' => 'N.º de confirmação:',
            // 'Pending' => '', // in conf number
            'Navan booking ID:'   => 'ID de reserva Navan:',
            'Booking details'     => 'Detalhes da reserva',
            'Room type:'          => 'Categoria de quarto:',
            'Check-in:'           => 'Check-in:',
            'Check-out:'          => 'Check-out:',
            'Cancellation policy' => "Política de cancelamento",
            // 'Fully refundable until' => "Gratis ändring eller avbokning fram till",
            'Non-refundable after booking' => 'Não reembolsável após reserva',
            'Traveler details'             => 'Detalhes do viajante',
            'Number of rooms'              => 'Número de quartos',
            'Price per night'              => 'Preço por noite',
            'Subtotal'                     => 'Subtotal',
            'Total'                        => 'Total',
        ],
        'es' => [
            'Your hotel reservation is' => "Tu reserva de hotel está",
            // 'canceled' => 'odwołany',
            'Confirmation:' => 'N.º de confirmación:',
            // 'Pending' => '', // in conf number
            'Navan booking ID:'      => 'Identificador de Navan:',
            'Booking details'        => 'Detalles de la reserva',
            'Room type:'             => 'Modalidad de habitación:',
            'Check-in:'              => 'Registro de entrada:',
            'Check-out:'             => 'Salida:',
            'Cancellation policy'    => "Política de cancelación",
            'Fully refundable until' => "Modificaciones y cancelación gratuitas hasta el",
            // 'Non-refundable after booking' => 'Não reembolsável após reserva',
            'Traveler details' => 'Detalles del viajero',
            'Number of rooms'  => 'Número de habitaciones',
            'Price per night'  => 'Precio por noche',
            'Subtotal'         => 'Subtotal',
            'Total'            => 'Total',
        ],
        'nl' => [
            'Your hotel reservation is' => "Je hotelreservering is",
            // 'canceled' => 'odwołany',
            'Confirmation:'          => 'Bevestigingsnummer:',
            'Pending'                => 'In afwachting', // in conf number
            'Navan booking ID:'      => 'Navan boeking-ID:',
            'Booking details'        => 'Boekingsdetails',
            'Room type:'             => 'Kamertype:',
            'Check-in:'              => 'Inchecken:',
            'Check-out:'             => 'Uitchecken:',
            'Cancellation policy'    => "Annuleringsvoorwaarden",
            'Fully refundable until' => "Gratis wijziging of annulering tot",
            // 'Non-refundable after booking' => 'Não reembolsável após reserva',
            'Traveler details' => 'Gegevens reiziger',
            'Number of rooms'  => 'Aantal kamers',
            'Price per night'  => 'Prijs per nacht',
            'Subtotal'         => 'Subtotaal',
            'Total'            => 'Totaal',
        ],
        'it' => [
            'Your hotel reservation is' => "Il tuo prenotazione hotel è stato",
            'canceled'                  => 'cancellato',
            'Confirmation:'             => 'Conferma #:',
            // 'Pending'                => 'In afwachting', // in conf number
            'Navan booking ID:'      => 'ID prenotazione Navan:',
            'Booking details'        => 'Dettagli della prenotazione',
            'Room type:'             => 'Tipo di camera:',
            'Check-in:'              => 'Check-in:',
            'Check-out:'             => 'Check-out:',
            'Cancellation policy'    => "Politica di cancellazione",
            'Fully refundable until' => ["Modifica o cancellazione gratuiti fino a",
                'Completamente rimborsabile fino al', ],
            // 'Non-refundable after booking' => 'Não reembolsável após reserva',
            'Traveler details' => 'Dettagli del viaggiatore',
            'Number of rooms'  => 'Numero di camere',
            'Price per night'  => 'Prezzo a notte',
            'Subtotal'         => 'Subtotale',
            'Total'            => 'Totale',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@navan.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Navan Inc')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Your hotel reservation is']) && $this->http->XPath->query("//text()[{$this->contains($dict['Your hotel reservation is'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Navan Inc.') !== false) {
                foreach (self::$dictionary as $dict) {
                    if (!empty($dict['Your hotel reservation is']) && $this->strposAll($text, $dict['Your hotel reservation is']) !== false
                        && !empty($dict['Booking details']) && $this->strposAll($text, $dict['Booking details']) !== false
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]navan\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Navan Inc')]")->length > 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Your hotel reservation is']) && $this->http->XPath->query("//text()[{$this->contains($dict['Your hotel reservation is'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
                    && !empty($dict['Check-out:']) && $this->http->XPath->query("//text()[{$this->eq($dict['Check-out:'])}]")->length > 0
                ) {
                    $this->lang = $lang;
                    $this->ParseHtml($email);
                    $type = 'Html';

                    break;
                }
            }
        }

        if (empty($email->getItineraries())) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, 'Navan Inc.') !== false) {
                    foreach (self::$dictionary as $lang => $dict) {
                        if (!empty($dict['Your hotel reservation is']) && $this->strposAll($text, $dict['Your hotel reservation is']) !== false
                            && !empty($dict['Booking details']) && $this->strposAll($text, $dict['Booking details']) !== false
                        ) {
                            $this->lang = $lang;
                            $this->ParsePdf($email, $text);
                            $type = 'Pdf';
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function ParseHtml(Email $email)
    {
        $this->logger->debug(__METHOD__);

        $h = $email->add()->hotel();

        // Travel Agency
        $h->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Navan booking ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Navan booking ID:'))}]"), ':'));

        // General
        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation:'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Pending'))}]"))) {
            $h->general()
                ->noConfirmation();
        } else {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})(?:\s+[A-Z]{1,5})?\s*$/");
            $h->general()
                ->confirmation($conf);
        }
        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler details'))}]/following::text()[normalize-space()][1]/ancestor::tr[2][not(.//text()[{$this->eq($this->t('Traveler details'))}])][count(*) = 2]/*[2]/descendant::text()[normalize-space()][1]"), true)
            ->cancellation(str_replace(['{', '}'], ['[', ']'], implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation policy'))}]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation policy'))}]][last()]/descendant::td[not(.//td)][normalize-space()][position() > 1]"))), true, true)
            ->status($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your hotel reservation is'))}]/ancestor::td[1]",
                null, true, "/^\s*{$this->opt($this->t('Your hotel reservation is'))}\s*([[:alpha:]]+)\s*$/u"))
        ;

        if (!empty($h->getStatus()) && (
            preg_match("/^\s*{$this->opt($this->t('canceled'))}\s*$/iu", $h->getStatus())
            || $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your hotel reservation is'))}]/following::text()[normalize-space()][1]/ancestor::*[contains(@style, 'color:#CF0000;')]")
        )) {
            $h->general()
                ->cancelled();
        }

        // Price
        $currency = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][last()]", null, true, "/^\s*([A-Z]{3})\s*$/");
        $total = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^\s*\D{0,3}(\d[\d\.\, ]+?)\D{0,3}\s*$/u");

        if (!$h->getCancelled() && !empty($currency) && !empty($total)) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Subtotal'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^\D*(\d[\d\.\, ]+?)\D*$/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $taxes = $this->http->XPath->query("//tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Subtotal'))}]]/following-sibling::tr[following-sibling::tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]]//tr[not(.//tr)][normalize-space()]");

            foreach ($taxes as $taxRoot) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $taxRoot);
                $value = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $taxRoot, true, "/^\D*(\d[\d\.\, ]+?)\D*$/");
                $h->price()
                    ->fee($name, PriceHelper::parse($value, $currency));
            }
        } elseif (!$h->getCancelled()) {
            $h->price()
                ->total(null);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//img[contains(@src, 'location-pin')]/preceding::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//img[contains(@src, 'location-pin')]/following::text()[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode("//img[contains(@src, 'phone')]/following::text()[normalize-space()][1]"));

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::tr[1]/following::tr[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out:'))}]/ancestor::tr[1]/following::tr[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Number of rooms'))}]]/*[normalize-space()][2]"));

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room type:'))}]/following::text()[normalize-space()][1]"))
            ->setRate($this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Price per night'))}]]/*[normalize-space()][2]"))
        ;

        $this->detectDeadLine($h);
    }

    public function ParsePdf(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $h = $email->add()->hotel();

        // Travel Agency
        $h->ota()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('Navan booking ID:'))}\n\s*([A-Z\d\-]{5,})\n/u", $text),
                trim($this->re("/\n\s*({$this->opt($this->t('Navan booking ID:'))})\n\s*[A-Z\d\-]+\n/u", $text), ':'));

        // General
        if (!empty($this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\n\s*({$this->opt($this->t('Pending'))})\n/u", $text))) {
            $h->general()
                ->noConfirmation();
        } else {
            $h->general()
                ->confirmation($this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\n\s*([A-Z\d\-]{5,})(?:\s+[A-Z]{1,5})?\n/u", $text));
        }
        $h->general()
            ->traveller($this->re("/\n\s*{$this->opt($this->t('Traveler details'))}\n\s*(?:[A-Z]{2} {2,})? +(.+)\n/u", $text), true)
            ->cancellation(preg_replace("/\n+/", "\n", $this->re("/\n\s*{$this->opt($this->t('Cancellation policy'))}\n\s*([\s\S]+?)\s*\n\s*{$this->opt($this->t('Traveler details'))}\n/u", $text)), true, true)
            ->status($this->re("/(?:^|\n) *{$this->opt($this->t('Your hotel reservation is'))} +([[:alpha:]]+)\n/u", $text))
        ;

        if (preg_match("/^\s*{$this->opt($this->t('canceled'))}\s*$/iu", $h->getStatus())) {
            $h->general()
                ->cancelled();
        }

        // Price
        if (!$h->getCancelled() && preg_match("/\n\s*{$this->opt($this->t('Total'))} {2,}\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *(?<currency>[A-Z]{3})\n/u", $text, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/\n\s*{$this->opt($this->t('Subtotal'))} +\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *[A-Z]{3}\n/", $text);

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $taxesText = array_filter(preg_split("/\n/", $this->re("/\n\s*{$this->opt($this->t('Subtotal'))} +.+\n+([\s\S]+?)\n+\n\s*{$this->opt($this->t('Total'))} {2,}/", $text)));

            foreach ($taxesText as $tt) {
                if (preg_match("/^\s*(\S.+) {3,}\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *[A-Z]{3}\s*$/", $tt, $m)) {
                    $h->price()
                        ->fee($m[1], PriceHelper::parse($m[2], $m['currency']));
                }
            }
        } elseif (!$h->getCancelled()) {
            $h->price()
                ->total(null);
        }

        // Hotel
        $hotelInfo = $this->re("/\n\s*{$this->opt($this->t('Booking details'))}\s*\n([\s\S]+?)\s*\n *(?:{$this->opt($this->t('Confirmation:'))}|{$this->opt($this->t('Room type:'))})/u", $text);

        if (preg_match("/^( *)(?<hotelName>\S.+)\n\s*(?<address>.+)\n\s*(?<phone>[+]?[\d\-]{5,})\s*$/u", $hotelInfo, $m)
            || preg_match("/^( *)(?<hotelName>\S.*(?:\n\\1\S.+)?)\s*\n\\1 {2,}(?<address>.+)\s*\n\\1 {2,}(?<phone>[+]?[\d\-]{5,})\s*$/u", $hotelInfo, $m)
        ) {
            $h->hotel()
                ->name(preg_replace('/\s+/', ' ', $m['hotelName']))
                ->address($m['address'])
                ->phone($m['phone']);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/\n\s*{$this->opt($this->t('Check-in:'))}\n+ *(\S.+)/", $text)))
            ->checkOut($this->normalizeDate($this->re("/\n\s*{$this->opt($this->t('Check-out:'))}\n+ *(\S.+)/", $text)))
            ->rooms($this->re("/\n\s*{$this->opt($this->t('Number of rooms'))} +(\d+)\n/", $text))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->re("/\n *{$this->opt($this->t('Room type:'))}\n\s*(.+)/", $text))
            ->setRate($this->re("/\n *{$this->opt($this->t('Price per night'))} *\D{0,3} ?(.+)/", $text))
        ;

        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/(?:^|\n)\s*{$this->opt($this->t('Fully refundable until'))}\s*(.*?)(?:\n|\s*$)/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline($this->normalizeDate($m[1]));
        }

        if (preg_match("/^\s*{$this->opt($this->t('Non-refundable after booking'))}\s*(?:\n|$)/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "=\"{$s}\"";
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
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Sat, Feb 22, 2025•3:00 PM
            "/^\s*[[:alpha:]]+\.?\s*[,\s]\s*([[:alpha:]]+)\s+(\d{1,2})\s*[\s,]\s*(\d{4})\s*[\s,•]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui",
            // Mi., 05. März 2025 • 15:00
            // mån 17 mars 2025•14:00
            // 24 févr. 2025 16:00
            "/^\s*(?:[[:alpha:]\-]+\.?\s*[,\s])?\s*(\d{1,2})\.?\s+([[:alpha:]]+)\.?\s+(\d{4})\s*[\s,•]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];

        $date = preg_replace($in, $out, $date);

        if ($this->lang === 'pt'
            && preg_match("/^\s*(?:[[:alpha:]\-]+\s*[,\s])?\s*(\d{1,2})\/(\d{2})\/(\d{4})\s*[\s,•]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui", $date, $m)
        ) {
            // terça, 25/02/2025 • 15:00
            $date = $m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4];
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function strposAll($haystack, $needles)
    {
        $needles = (array) $needles;

        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
