<?php

namespace AwardWallet\Engine\solmelia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "solmelia/it-168252251.eml, solmelia/it-168762014.eml, solmelia/it-170629703.eml, solmelia/it-171886283.eml, solmelia/it-178307954.eml, solmelia/it-809185260-de.eml";
    public $subjects = [
        // en
        'Booking Confirmation',
        'Confirmation de reservation – LOC',
        'Booking Cancellation - LOC',
        // es
        'Confirmación de la reserva',
        'Cancelación de la reserva - LOC:',
        // it
        'Conferma di prenotazione - LOC',
        // pt
        'Cancelamento da reserva - LOC',
        'Confirmação da reserva - LOC',
        // de
        'Buchungsbestätigung – Nr.',
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['You can check in online from', 'The reservation has been cancelled', 'Your reservation has been confirmed'],
        'es' => ['El check-in online se puede realizar a partir', 'Tu reserva está confirmada', 'La reserva se ha cancelado'],
        'pt' => ['Você pode fazer check-in online a partir', 'Sua reserva está confirmada', 'Esperamos vê-lo em breve em um de nossos hotéis'],
        'fr' => ['Votre réservation a été confirmée'],
        'it' => ['La tua prenotazione è confermata'],
        'de' => ['Ihre Reservierung ist bestätigt'],
    ];

    public static $dictionary = [
        "en" => [ // it-168762014.eml
            'dateSeparator'     => ['from', 'before'],
            'Room'              => ['Room', 'Rooms'],
            'Adults'            => ['Adult', 'Adults'],
            'Total'             => ['Total', 'Total amount'],
            'cancelledText'     => 'The reservation has been cancelled',
            'Occupancy'         => 'Occupancy',
            'Payment details'   => 'Payment details',
            'statusPhrases'     => 'has been',
            'statusVariants'    => ['confirmed', 'cancelled', 'canceled'],
        ],

        "es" => [ // it-170629703.eml, it-178307954.eml
            'Booking reference' => 'Localizador',
            'Dates'             => 'Fechas',
            'Guest name'        => 'Titular de la reserva',
            'If cancel within'  => 'Si cancela',
            'Address'           => 'Dirección',
            'Contact'           => 'Contacto',
            'Arrival'           => 'Llegada',
            'dateSeparator'     => ['a partir de las', 'antes de las', 'a partir de las after'],
            'Departure'         => 'Salida',
            'Occupancy'         => 'Ocupación',
            'Room'              => 'Habitación',
            'Room '             => 'Habitación ',
            'Adults'            => 'Adultos',
            //'Child' => '',
            'Total'           => ['Importe total', 'Total'],
            'points'          => 'puntos',
            'Manage Booking'  => 'Gestionar Reserva',
            'Payment details' => 'Detalle del pago',
            'statusPhrases'   => 'reserva está',
            'statusVariants'  => ['confirmada', 'cancelado'],
            'cancelledText'   => 'La reserva se ha cancelado',
        ],

        "pt" => [ // it-168252251.eml
            'Booking reference' => 'Referência da reserva',
            'Dates'             => 'Datas',
            'Guest name'        => 'Titular da reserva',
            'If cancel within'  => 'Se cancelar',
            'Address'           => 'Endereço',
            'Contact'           => 'Contato',
            'Arrival'           => 'Chegada',
            'dateSeparator'     => ['a partir das', 'antes', 'até ás'],
            'Departure'         => 'Saída',
            'Occupancy'         => 'Ocupação',
            'Room'              => ['Quarto', 'Quartos'],
            'Room '             => ['Quarto ', 'Quartos '],
            'Adults'            => 'Adultos',
            'Child'             => 'Criança',
            //'Total' => '',
            'points'            => 'pontos',
            'Manage Booking'    => 'Gerenciar reserva',
            'Payment details'   => 'Detalhes do pagamento',
            'statusPhrases'     => 'reserva está',
            'statusVariants'    => 'confirmada',
            'cancelledText'     => 'A reserva foi cancelada',
        ],
        "fr" => [ // it-171886283.eml
            'Booking reference' => 'Référence de la réservation',
            //            'Dates' => '',
            'Guest name'        => 'Personne effectuant la réservation',
            'If cancel within'  => "En cas d'annuler",
            'Address'           => 'Adresse',
            'Contact'           => 'Contact',
            'Arrival'           => "Arrivée",
            'dateSeparator'     => ["à partir de", "avant"],
            'Departure'         => 'Départ',
            'Occupancy'         => 'Occupation',
            //'Room'              => [],
            //'Room '             => [],
            'Adults'            => ['Adult', 'Adultes'],
            //'Child' => '',
            'Total' => 'Le total',
            //'points'          => '',
            'Manage Booking'  => 'Gérer la réservation',
            'Payment details' => 'Détails de paiement',
            'statusPhrases'   => 'réservation a été',
            'statusVariants'  => 'confirmée',
            //            'cancelledText'     => '',
        ],
        "it" => [
            'Booking reference' => 'Riferimento della prenotazione',
            'Dates'             => 'Date',
            'Guest name'        => 'Persona che effettua la prenotazione',
            'If cancel within'  => "Per cancellazioni ",
            'Address'           => 'Indirizzo',
            'Contact'           => 'Contatti',
            'Arrival'           => "Arrivo",
            'dateSeparator'     => [", dalle", "entro le"],
            'Departure'         => 'Partenza',
            'Occupancy'         => 'Occupanti',
            'Room'              => ['Camera'],
            'Room '             => ['Camera '],
            'Adults'            => ['Adulti', 'Adulto'],
            //'Child' => '',
            'Total'           => ['Importo totale', 'Totale'],
            'points'          => 'punti',
            'Manage Booking'  => 'Gestisci la prenotazione',
            'Payment details' => 'Dettagli del pagamento',
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            //            'cancelledText'     => '',
        ],
        "de" => [
            'Booking reference' => 'Buchungsreferenz',
            'Dates'             => 'Datum',
            'Guest name'        => 'Person, die die Buchung getätigt hat',
            'If cancel within'  => 'Bei Stornierungen',
            'Address'           => 'Adresse',
            'Contact'           => 'Kontakt',
            'Arrival'           => 'Anreise',
            'dateSeparator'     => ['ab', 'vor'],
            'Departure'         => 'Abreise',
            'Occupancy'         => 'Belegung',
            'Room'              => 'Zimmer',
            'Room '             => 'Zimmer ',
            'Adults'            => ['Erwachsener', 'Erwachsene'],
            'Child'             => 'Kind',
            'Total'             => 'Gesamt',
            // 'points' => '',
            'Manage Booking'  => 'Buchung verwalten',
            'Payment details' => 'Zahlungsdaten',
            'statusPhrases'   => 'Ihre Reservierung ist',
            'statusVariants'  => 'bestätigt',
            // 'cancelledText' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.melia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $href = ['.melia.com', '.yourmeliahotel.com'];

        if ($this->http->XPath->query("//a[{$this->contains($href, "@href")} or {$this->contains($href, "@originalsrc")}]")->length === 0
            && $this->http->XPath->query('//text()[contains(normalize-space(),"Meliá Hotels") and contains(normalize-space(),"All rights reserved")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Occupancy']) && (!empty($dict['Payment details']) || !empty($dict['cancelledText']))
                && $this->http->XPath->query("//text()[{$this->contains($dict['Occupancy'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Payment details'] ?? [])} or {$this->contains($dict['cancelledText'] ?? [])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.melia\.com$/i', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.?\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s+worden|\s*[,;:!?]|$)/"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        }

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space()][1]", null, true, "/^([\dA-Z]{8,})/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest name'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u"), true)
            ->cancellation($this->http->FindSingleNode("//text()[{$this->starts($this->t('If cancel within'))}]"), true, true);

        $cancelled = false;

        if ($this->http->FindSingleNode("//text()[{$this->starts($this->t('cancelledText'))}]")) {
            $cancelled = true;
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/preceding::text()[normalize-space()][1]"))
        ;

        if ($cancelled === true) {
            $h->hotel()
                ->noAddress();
        } else {
            $h->hotel()
                ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/following::text()[normalize-space()][1]/ancestor::td[1]"))
                ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Contact'))}]/following::text()[normalize-space()][1]/ancestor::td[1]",
                    null, true, "/^\s*([+\d\s\(\)]+)/"));
        }

        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (empty($checkIn) || empty($checkOut)) {
            $dates = $this->http->FindNodes("//tr[{$this->eq($this->t('Dates'))}]/following-sibling::tr[normalize-space()][1]//text()[normalize-space()]");

            if (count($dates) === 3) {
                $checkIn = $dates[0];
                $checkOut = $dates[1];
            }
        }
        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $occupancy = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Occupancy'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<room>\d+)\s*{$this->opt($this->t('Room'))}[+\s]+(?<adult>\d+)\s*{$this->opt($this->t('Adults'))}(?:[+\s]+(?<kids>\d+)\s*[[:alpha:]]+)?\s*$/u", $occupancy, $m)) {
            $h->booked()
                ->rooms($m['room'])
                ->guests($m['adult']);

            if (isset($m['kids']) && !empty($m['kids'])) {
                $h->booked()
                    ->kids($m['kids']);
            }
        }

        $rateType = $this->http->FindSingleNode("//h4[{$this->eq($this->t('Rate type'))}]/following-sibling::*[normalize-space()]");

        $roomNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Room '))}]");

        foreach ($roomNodes as $roomRoot) {
            if (!preg_match("/^\s*{$this->opt($this->t('Room '))}\s*\d+\s*$/", $roomRoot->nodeValue)) {
                break;
            }
            $room = $h->addRoom();

            $room->setType($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $roomRoot));

            $room->setDescription($this->http->FindSingleNode("./following::text()[normalize-space()][2]", $roomRoot));

            $room->setRateType($rateType, false, true);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[{$this->eq($this->t('Total'))}][1]/following::text()[normalize-space()][1]/ancestor::td[1]");

        if (preg_match("/^\s*(?<total>\d[\d\,\.]*)\s*(?<currency>\S{1,3})\s*$/u", $price, $m)
            || preg_match("/^\s*(?<currency>\S{1,3})\s*(?<total>\d[\d\,\.]*)\s*$/u", $price, $m)
            || preg_match("/^\s*(?<total>\d[\d\,\.]*)\s*(?<currency>\S{1,3})[\s+]+(?<points>\d[\d\.,]*\s*{$this->opt($this->t('points'))})\s*$/u", $price, $m)
            || preg_match("/^\s*(?<currency>\S{1,3})\s*(?<total>\d[\d\,\.]*)[\s+]+(?<points>\d[\d\.,]*\s*{$this->opt($this->t('points'))})\s*$/u", $price, $m)
        ) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            if (isset($m['points'])) {
                $h->price()
                    ->spentAwards($m['points']);
            }
        } elseif (preg_match("/^\s*(?<points>\d[\d\.,]*\s*{$this->opt($this->t('points'))})\s*$/u", $price, $m)
        ) {
            $h->price()
                ->spentAwards($m['points']);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseHotel($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            // Monday, 1 August 2022 from 14:00
            "/^[-[:alpha:]]+\s*,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})[,\s]*{$this->opt($this->t('dateSeparator'))}\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\D*$/iu",
            // Monday, 1 August 2022
            "/^[-[:alpha:]]+\s*,\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP'   => ['£'],
            'EUR'   => ['€'],
            'THB'   => ['฿'],
            'INR'   => ['Rs.'],
            'BRL'   => ['R$'],
            'IDR'   => ['Rp'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t($word))}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectDeadLine(Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/If (?i)cancell? within (\d+) Hour\(s\) before arrival or customers are no show/u", $cancellationText, $m)
            || preg_match("/Si (?i)cancela (\d+) Hora\(s\) antes de la llegada o no se presenta/u", $cancellationText, $m)
            || preg_match("/Se (?i)cancelar (\d+) Hora\(s\) antes da chegada ou não comparência/u", $cancellationText, $m)
            || preg_match("/En (?i)cas d'annuler (\d+) Heure\(s\) avant l'arrivée ou non présentation/u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        } elseif (preg_match("/Se (?i)cancelar (\d+) Dia\(s\) antes da chegada ou não comparência/u", $cancellationText, $m)
            || preg_match("/If (?i)cancell? within (\d+) Day\(s\) before arrival/", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        } elseif (preg_match("/^Se cancell?ar, modificar a reserva ou não comparência 100 ?% do total será cobrado como penalidade[.;!\s]*$/i", $cancellationText) // pt
        ) {
            $h->booked()->nonRefundable();
        }
    }
}
