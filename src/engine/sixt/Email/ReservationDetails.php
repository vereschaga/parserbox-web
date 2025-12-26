<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "sixt/it-282880041.eml, sixt/it-337840682.eml, sixt/it-413867489.eml, sixt/it-428652947.eml, sixt/it-430974260.eml, sixt/it-530325692.eml, sixt/it-687894976.eml";
    public $subjects = [
        // en
        // Your Stuttgart reservation 9700412579 is confirmed
        '/Your booking has been updated/',
        '/Your .+ reservation \d+ is confirmed/',
        // de
        '/Ihre Buchung \d+ in .+ ist bestätigt/',
        // es
        '/solicitud de reserva recibida/',
        // fr
        '/Votre réservation a été mise à jour/',
        '/Votre réservation.*est confirmée\./',
        '/Votre réservation.*a été mise à jour\./',
        //pl
        '/Twoja rezerwacja.*została potwierdzona./',
        // it
        '/La tua .+ prenotazione \d+ è confermata/',
        '/La tua prenotazione .+ \d+ è stata aggiornata/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'Reservation number'  => ['Reservation number', 'Reservation', 'Booking'],
            'Hello,'              => ['Hello,', 'Great choice,', 'Thank you for booking with us,'],
            'statusPhrases'       => ['YOUR RESERVATION IS', 'YOUR BOOKING HAS BEEN', 'is '],
            'statusVariants'      => ['confirmed', 'UPDATED'],
            'Reservation details' => ['Reservation details', 'Your reservation details', 'New reservation details', 'YOUR RESERVATION', 'YOUR BOOKING'],
            'Pick-up'             => ['Pick-up', 'Pickup'],
            //            'Opening hours:' => '',
            //            'Location info' => '',
            //            'Return' => '',
            'Vehicle category'              => ['Vehicle category', 'Your guaranteed model', 'Your requested model', 'Your guaranteed vehicle', 'Your desired model'],
            'Estimated total (taxes incl.)' => ['Estimated total (taxes incl.)', 'Paid total (taxes incl.)', 'Total due (taxes included)', 'Total paid (taxes included)', 'Total (taxes included)', 'Total paid in advance', 'Estimated total at pickup', 'Total payment at pickup', 'Total at pickup', 'Total', 'Estimated total'],
            // 'Pickup at' => '',
            // 'Return at' => '',
            // 'Get directions' => '',
        ],
        "de" => [
            'Reservation number'            => ['Buchungsnummer', 'Buchung'],
            'Hello,'                        => ['Hallo', 'Gute Entscheidung,'],
            'statusPhrases'                 => ['IHRE BUCHUNG IST'],
            'statusVariants'                => ['BESTÄTIGT'],
            'Reservation details'           => ['Buchungsdetails', 'IHRE BUCHUNG IST'],
            'Pick-up'                       => ['Abholung', 'Abholung:'],
            'Opening hours:'                => 'Öffnungszeiten:',
            'Location info'                 => 'Infos zur Station',
            'Return'                        => ['Rückgabe', 'Rückgabe:'],
            'Vehicle category'              => ['Ihre gebuchte Fahrzeugkategorie:', 'Fahrzeugkategorie', 'Ihr garantiertes Modell', 'Fahrzeugkategorie'],
            'Estimated total (taxes incl.)' => ['Geschätzter Gesamtpreis (inkl. Steuern)', 'Gesamtpreis (inkl. Steuern)', 'Gesamtpreis bezahlt (inkl. Steuern)', 'Im Voraus bezahlter Gesamtbetrag', 'Gesamtsumme bei Abholung'],
            'Pickup at'                     => ['Abholung:'],
            'Return at'                     => ['Rückgabe:'],
            // 'Get directions' => '',
        ],
        "es" => [
            'Reservation number'            => ['Número de reserva'],
            'Hello,'                        => 'Hola:',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            'Reservation details'           => 'Detalles de la reserva',
            'Pick-up'                       => 'Recogida',
            //'Opening hours:'                => 'Öffnungszeiten:',
            //'Location info'                 => 'Infos zur Station',
            'Return'                        => 'Devolución',
            'Vehicle category'              => ['El modelo que solicitaste'],
            'Estimated total (taxes incl.)' => ['Total adeudado (impuestos incluidos)'],
            // 'Pickup at' => '',
            // 'Return at' => '',
            // 'Get directions' => '',
        ],
        "fr" => [
            'Reservation number'            => ['Numéro de réservation', 'Réservation'],
            'Hello,'                        => 'Bonjour,',
            'statusPhrases'                 => ['VOTRE RÉSERVATION A ÉTÉ', 'est'],
            'statusVariants'                => ['confirmée', 'MISE À JOUR'],
            'Reservation details'           => ['Informations de la réservation', 'Détail de la réservation', 'VOTRE RÉSERVATION'],
            'Pick-up'                       => 'Retrait',
            //'Opening hours:'                => 'Öffnungszeiten:',
            //'Location info'                 => 'Infos zur Station',
            'Return'                        => 'Retour',
            'Vehicle category'              => 'Catégorie du véhicule',
            'Estimated total (taxes incl.)' => ['Total payé (taxes incluses)', 'Total dû (taxes incluses)', 'Total (taxes comprises)', 'Paiement total à la collecte', 'Total'],
            'Pickup at'                     => 'Retrait à',
            'Return at'                     => 'Retour à',
            // 'Get directions' => '',
        ],
        "pl" => [
            'Reservation number'            => 'Numer rezerwacji',
            'Hello,'                        => 'Cześć,',
            'statusPhrases'                 => ['została'],
            'statusVariants'                => ['potwierdzona'],
            'Reservation details'           => ['Szczegóły rezerwacji'],
            'Pick-up'                       => 'Odbiór',
            //'Opening hours:'                => 'Öffnungszeiten:',
            //'Location info'                 => 'Infos zur Station',
            'Return'                        => 'Zwrot',
            'Vehicle category'              => 'Kategoria pojazdu',
            'Estimated total (taxes incl.)' => ['Należność razem (z podatkiem)'],
            // 'Pickup at' => '',
            // 'Return at' => '',
            // 'Get directions' => '',
        ],
        "it" => [
            'Reservation number'            => 'Numero di prenotazione',
            'Hello,'                        => 'Buongiorno,',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            'Reservation details'           => ['Dettagli della prenotazione'],
            'Pick-up'                       => 'Ritiro',
            //'Opening hours:'                => 'Öffnungszeiten:',
            //'Location info'                 => 'Infos zur Station',
            'Return'                        => ['Restituzione', 'Riconsegna'],
            'Vehicle category'              => 'Categoria del veicolo',
            'Estimated total (taxes incl.)' => ['Totale (tasse incluse)', 'Totale pagato (tasse incluse)'],
            // 'Pickup at' => '',
            // 'Return at' => '',
            // 'Get directions' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // $this->logger->debug("//node()[{$this->contains(['Your SIXT team –', 'Il tuo team di SIXT –', 'Sixt Vienna City team'])}]");

        if ($this->http->XPath->query("//a[contains(@href, '.sixt.com')]")->length === 0
            && $this->http->XPath->query("//node()[{$this->contains(['Your SIXT team –', 'Il tuo team di SIXT –', 'Sixt Vienna City team'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sixt\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[.:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4.19PM    |    2:00 p. m.    |    3pm
        ];

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation number'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{4,35})\s*$/"));

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello,'))}]", null, "/^{$this->opt($this->t('Hello,'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $r->general()->traveller($traveller);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $r->general()->status($status);
        }

        $r->car()
            ->image($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle category'))}]/ancestor::td[.//text()[normalize-space()][1][{$this->eq($this->t('Vehicle category'))}]]/following-sibling::td[not(normalize-space())][count(.//img) = 1]//img[contains(@src, 'sixt') and contains(@src, 'http')][1]/@src"), true, true)
            ->type($this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle category'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Your reservation details'))})]"));

        $patterns['dateTime'] = "/^(?<date>.{3,}?)\s*(?:at|um|à|\|)\s*(?<time>{$patterns['time']})/u";

        $regexp = "/^\s*(?:{$this->opt($this->t('Pick-up'))}\s*\n|{$this->opt($this->t('Return'))}\s*\n|)\s*(?<name>.+)\n(?<date>.+)\n\s*{$this->opt($this->t('Opening hours:'))}(?<hours>.+)\s*{$this->opt($this->t('Location info'))}/";
        $regexp2 = "/^\s*(?:{$this->opt($this->t('Pick-up'))}\s*\n|{$this->opt($this->t('Return'))}\s*\n|)\s*(?<name>.+)\n(?<date>.+)\s*$/";

        $pickUp = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Return'))}])][last()]//text()[normalize-space()]"));

        if (preg_match($regexp, $pickUp, $matches) || preg_match($regexp2, $pickUp, $matches)) {
            if (preg_match($patterns['dateTime'], $matches['date'], $m)) {
                $datePickUp = strtotime($this->normalizeDate($m['date']));
                $timePickUp = $this->normalizeTime($m['time']);
            } else {
                $datePickUp = $timePickUp = null;
            }

            $r->pickup()
                ->location($matches['name'])
                ->date(strtotime($timePickUp, $datePickUp))
                ->openingHours(empty($matches['hours']) ? null : $matches['hours'], false, true)
            ;
        }

        $dropOff = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Pick-up'))}])][last()]//text()[normalize-space()]"));

        if (preg_match($regexp, $dropOff, $matches) || preg_match($regexp2, $dropOff, $matches)) {
            if (preg_match($patterns['dateTime'], $matches['date'], $m)) {
                $dateDropOff = strtotime($this->normalizeDate($m['date']));
                $timeDropOff = $this->normalizeTime($m['time']);
            } else {
                $dateDropOff = $timeDropOff = null;
            }

            $r->dropoff()
                ->location($matches['name'])
                ->date(strtotime($timeDropOff, $dateDropOff))
                ->openingHours(empty($matches['hours']) ? null : $matches['hours'], false, true)
            ;
        }

        if (empty($pickUp) && empty($dropOff)) {
            // it-530325692.eml, it-687894976.eml

            $pickUpName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup at'))}]", null, true, "/{$this->opt($this->t('Pickup at'))}\s*(.+)/");
            $r->pickup()->location($pickUpName);

            $pickUpDateVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup at'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][string-length()>5][not({$this->contains($this->t('Get directions'))})]");

            if (preg_match($patterns['dateTime'], $pickUpDateVal, $m)) {
                $datePickUp = strtotime($this->normalizeDate($m['date']));
                $timePickUp = $this->normalizeTime($m['time']);
            } else {
                $datePickUp = $timePickUp = null;
            }

            $r->pickup()->date(strtotime($timePickUp, $datePickUp));

            $dropOffName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Return at'))}]", null, true, "/{$this->opt($this->t('Return at'))}\s*(.+)/");
            $r->dropoff()->location($dropOffName);

            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Return at'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[normalize-space()][string-length()>5][not({$this->contains($this->t('Get directions'))})]");

            if (preg_match($patterns['dateTime'], $dropOffDate, $m)) {
                $dateDropOff = strtotime($this->normalizeDate($m['date']));
                $timeDropOff = $this->normalizeTime($m['time']);
            } else {
                $dateDropOff = $timeDropOff = null;
            }

            $r->dropoff()->date(strtotime($timeDropOff, $dateDropOff));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Estimated total (taxes incl.)'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Estimated total (taxes incl.)'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/') // it-282880041.eml
        ;

        if (preg_match("/^\s*(?<amount>\d[\d.,’ ]*?)\s*(?<currency>[^\d\s]{1,5})\s*$/u", $totalPrice, $matches)
            || preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d.,’ ]*?)\s*$/u", $totalPrice, $matches)
        ) {
            // 1 162,03 €    |    $ 1,705.73
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        } else {
            // for error
            $email->price()
                ->total(null);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseCar($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation details'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Reservation details'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})$/u', $text, $m)) {
            // Mi, 22. Feb 2023    |    Samstag, 20. Jul, 2024    |    Vendredi, 01 Nov., 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]+)[,.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Fr. Jan 13. 2023    |    Wed, Jul 26, 2023    |    Mar. Févr. 28. 2023    |    Tuesday, Oct 10, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/(\d)[ ]*[.][ ]*(\d)/', // 01.55 PM    ->    01:55 PM
        ], [
            '$1:$2',
        ], $s);

        return $s;
    }

    private function currency(?string $s): ?string
    {
        $sym = [
            '₣' => 'CHF',
            'A$'=> 'AUD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
