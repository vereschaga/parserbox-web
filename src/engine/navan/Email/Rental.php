<?php

namespace AwardWallet\Engine\navan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Rental extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectSubjects = [
        // en
        'Your rental car for ',
        // de
        'Ihr Mietwagen für ',
        'Sie haben Ihren Mietwagen in ',
        // es
        '¡Tu coche de alquiler para ',
        'Has cancelado tu coche de alquiler en ',
        // it
        "L'auto a noleggio per ",
        "Hai cancellato l'auto a noleggio a",
        // fr
        'Votre location de voiture pour',
        'Vous avez annulé votre location de voiture à',
        // sv
        'Din hyrbil för ',
        // pl
        'Twój wypożyczony samochód',
    ];

    public $pdfNamePattern = ".*pdf";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Your rental car is'  => 'Your rental car is',
            'canceled'            => ['canceled', 'cancelled'],
            'Confirmation:'       => 'Confirmation:',
            'Navan booking ID:'   => 'Navan booking ID:',
            'Booking details'     => 'Booking details',
            'Pick-up'             => 'Pick-up',
            'Open hours:'         => 'Open hours:',
            'Drop-off'            => 'Drop-off',
            'Cancellation policy' => 'Cancellation policy',
            'Traveler'            => 'Traveler',
            'Subtotal'            => 'Subtotal',
            'Total'               => 'Total',
        ],
        'de' => [
            'Your rental car is'  => 'Ihr Mietwagen wurde',
            'canceled'            => 'storniert',
            'Confirmation:'       => 'Bestätigungsnummer:',
            'Navan booking ID:'   => 'Navan Buchungs-ID:',
            'Booking details'     => 'Buchungsdetails',
            'Pick-up'             => 'Abholung',
            'Open hours:'         => 'Öffnungszeiten:',
            'Drop-off'            => 'Drop-off',
            'Cancellation policy' => 'Stornierungsrichtlinie',
            'Traveler'            => 'Reisende/r',
            'Subtotal'            => 'Zwischensumme',
            'Total'               => 'Gesamt',
        ],
        'es' => [
            'Your rental car is'  => 'Tu coche de alquiler está',
            'canceled'            => 'cancelado',
            'Confirmation:'       => 'N.º de confirmación:',
            'Navan booking ID:'   => 'Identificador de Navan:',
            'Booking details'     => 'Detalles de la reserva',
            'Pick-up'             => 'Recogida',
            'Open hours:'         => 'Horario de atención al público:',
            'Drop-off'            => 'Entrega',
            'Cancellation policy' => 'Política de cancelación',
            'Traveler'            => 'Viajero',
            'Subtotal'            => 'Subtotal',
            'Total'               => 'Total',
        ],
        'it' => [
            'Your rental car is'  => 'Il tuo auto a noleggio è stato',
            'canceled'            => 'cancellato',
            'Confirmation:'       => 'Conferma #:',
            'Navan booking ID:'   => 'ID prenotazione Navan:',
            'Booking details'     => 'Dettagli della prenotazione',
            'Pick-up'             => 'Ritiro',
            'Open hours:'         => 'Orari di apertura:',
            'Drop-off'            => 'Consegna',
            'Cancellation policy' => 'Politica di cancellazione',
            'Traveler'            => 'Viaggiatore',
            'Subtotal'            => 'Subtotale',
            'Total'               => 'Totale',
        ],
        'fr' => [
            'Your rental car is'  => 'Votre voiture de location est',
            'canceled'            => 'annulé',
            'Confirmation:'       => 'N° de confirmation :',
            'Navan booking ID:'   => 'ID de réservation Navan :',
            'Booking details'     => 'Détails de la réservation',
            'Pick-up'             => 'Prise en charge',
            'Open hours:'         => "Heures d'ouverture :",
            'Drop-off'            => 'Drop-off',
            'Cancellation policy' => "Politique d'annulation",
            'Traveler'            => 'Traveler',
            'Subtotal'            => 'Sous-total',
            'Total'               => 'Total',
        ],
        'sv' => [
            'Your rental car is' => 'Din hyrbil är',
            // 'canceled' => 'annulé',
            'Confirmation:'       => 'Bekräftelse #:',
            'Navan booking ID:'   => 'Navan-boknings-ID:',
            'Booking details'     => 'Bokningsuppgifter',
            'Pick-up'             => 'Upphämtning',
            'Open hours:'         => "Öppettider:",
            'Drop-off'            => 'Avlämning',
            'Cancellation policy' => "Avbokningspolicy",
            'Traveler'            => 'Resenär',
            'Subtotal'            => 'Delsumma',
            'Total'               => 'Totalsumma',
        ],
        'pl' => [
            'Your rental car is' => 'Twój samochód do wynajęcia jest',
            // 'canceled' => 'annulé',
            'Confirmation:'       => 'Potwierdzenie nr:',
            'Navan booking ID:'   => 'Identyfikator rezerwacji Navan:',
            'Booking details'     => 'Szczegóły rezerwacji',
            'Pick-up'             => 'Odbiór',
            'Open hours:'         => "Godziny otwarcia:",
            'Drop-off'            => 'Podstawienie',
            'Cancellation policy' => "Zasady anulowania",
            'Traveler'            => 'Podróżny',
            'Subtotal'            => 'Suma cząstkowa',
            'Total'               => 'Ogółem',
        ],
    ];

    private $detectRentalProviders = [
        'national' => [
            'National',
        ],
        'perfectdrive' => [
            'Budget',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'hertz' => [
            'Hertz',
        ],
        'avis' => [
            'Avis',
        ],
        'sixt' => [
            'Sixt',
        ],
        'europcar' => [
            'Europcar',
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
                if (!empty($dict['Your rental car is']) && $this->http->XPath->query("//text()[{$this->contains($dict['Your rental car is'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
                    && !empty($dict['Drop-off']) && $this->http->XPath->query("//text()[{$this->eq($dict['Drop-off'])}]")->length > 0
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
                    if (!empty($dict['Your rental car is']) && $this->strposAll($text, $dict['Your rental car is']) !== false
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

    public function ParseHtml(Email $email)
    {
        $this->logger->debug(__METHOD__);

        $r = $email->add()->rental();

        // Travel Agency
        $r->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Navan booking ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})\s*$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Navan booking ID:'))}]"), ':'));

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})(?:\s+[A-Z]{1,5})?\s*$/"))
            ->cancellation(implode('. ', $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation policy'))}]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation policy'))}]][last()]/descendant::td[not(.//td)][normalize-space()][position() > 1]")), true, true)
            ->status($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your rental car is'))}]/ancestor::td[1]",
                null, true, "/^\s*{$this->opt($this->t('Your rental car is'))}\s*([[:alpha:]]+)\s*$/u"))
        ;

        if (!empty($r->getStatus()) && (
            preg_match("/^\s*{$this->opt($this->t('canceled'))}\s*$/iu", $r->getStatus())
            || $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your rental car is'))}]/following::text()[normalize-space()][1]/ancestor::*[contains(@style, 'color:#CF0000;')]")
        )) {
            $r->general()
                ->cancelled();
        }
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler'))}]/following::text()[normalize-space()][1]/ancestor::tr[2][not(.//text()[{$this->eq($this->t('Traveler'))}])][count(*) = 2]/*[2]/descendant::text()[normalize-space()][1]");

        if (empty($traveller) && $r->getCancelled()) {
        } else {
            $r->general()->traveller($traveller, true);
        }

        // Price
        $currency = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][last()]", null, true, "/^\s*([A-Z]{3})\s*$/");
        $total = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^\s*\D{0,3}(\d[\d\.\, ]+?)\D{0,3}\s*$/");

        if (!$r->getCancelled() && !empty($currency) && !empty($total)) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//tr[*[normalize-space()][1][{$this->eq($this->t('Subtotal'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^\D*(\d[\d\.\, ]+?)\D*$/");

            if (!empty($cost)) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $taxes = $this->http->XPath->query("//tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Subtotal'))}]]/following-sibling::tr[following-sibling::tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}]]]//tr[not(.//tr)][normalize-space()]");

            foreach ($taxes as $taxRoot) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $taxRoot);
                $value = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $taxRoot, true, "/^\D*(\d[\d\.\, ]+?)\D*$/");
                $r->price()
                    ->fee($name, PriceHelper::parse($value, $currency));
            }
        } elseif (!$r->getCancelled()) {
            $r->price()
                ->total(null);
        }

        $rentalInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Pick-up'))}]/ancestor::*[.//text()[{$this->eq($this->t('Drop-off'))}]][1]/descendant::tr[not(.//tr)]"));
        // $this->logger->debug('$rentalInfo = '.print_r( $rentalInfo,true));
        $re = "/^\s*(?<time>\d{1,2}:\d{2}.*)\n\s*(?<date>.+)\n\s*(?<address>.+)\n\s*{$this->opt($this->t('Open hours:'))} *(?<openHours>.+)(?<phone>\n\s*[\d\W]*\d+[\d\W]*)?(?:\s*$|\n\s*)/";
        // $this->logger->debug('$re = '.print_r( $re,true));

        // Pick Up
        $pickUpText = $this->re("/\n\s*{$this->opt($this->t('Pick-up'))}\s*\n([\s\S]+?)\n\s*{$this->opt($this->t('Drop-off'))}\s*\n/u", $rentalInfo);

        if (preg_match($re, $pickUpText, $m)) {
            $r->pickup()
                ->location($m['address'])
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                ->phone(isset($m['phone']) ? trim($m['phone']) : null, true, true)
                ->openingHours($m['openHours']);
        }

        // Drop Off
        $dropOffText = $this->re("/\n\s*{$this->opt($this->t('Drop-off'))}\s*\n\s*([\s\S]+)/u", $rentalInfo);

        if (preg_match($re, $dropOffText, $m)) {
            $r->dropoff()
                ->location($m['address'])
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                ->phone(isset($m['phone']) ? trim($m['phone']) : null, true, true)
                ->openingHours($m['openHours']);
        }

        // Car
        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/following::text()[normalize-space()][1]/ancestor::tr[2][not(.//text()[{$this->eq($this->t('Booking details'))}])][count(*) = 2]/*[2]/descendant::text()[normalize-space()][2]"));
        $company = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/following::text()[normalize-space()][1]/ancestor::tr[2][not(.//text()[{$this->eq($this->t('Booking details'))}])][count(*) = 2]/*[2]/descendant::text()[normalize-space()][1]");

        $provider = $this->getRentalProviderByKeyword($company);

        if (!empty($provider)) {
            $r->setProviderCode($provider);

            $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler'))}]/following::text()[normalize-space()][position() < 10][{$this->eq($company)}]/following::text()[normalize-space()][1]");

            if (preg_match("/^\s*([A-Z]+[A-Z\s\-]*?) (•{4}[A-Z\d]{4})\s*$/u", $account, $m)) {
                $r->program()
                    ->account($m[2], true, $m[1]);
            }
        } else {
            $r->extra()->company($company);
        }
    }

    public function ParsePdf(Email $email, string $text)
    {
        $this->logger->debug(__METHOD__);

        $r = $email->add()->rental();

        // Travel Agency
        $r->ota()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('Navan booking ID:'))}\n\s*([A-Z\d\-]{5,})\n/u", $text),
                trim($this->re("/\n\s*({$this->opt($this->t('Navan booking ID:'))})\n\s*[A-Z\d\-]+\n/u", $text), ':'));

        // General
        $r->general()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('Confirmation:'))}\n\s*([A-Z\d\-]{5,})(?:\s+[A-Z]{1,5})?\n/u", $text))
            ->cancellation($this->re("/\n\s*{$this->opt($this->t('Cancellation policy'))}\n\s*([\s\S]+?)\s*\n\s*{$this->opt($this->t('Traveler'))}\n/u", $text), true, true)
            ->status($this->re("/(?:^|\n) *{$this->opt($this->t('Your rental car is'))} +([[:alpha:]]+)\n/u", $text))
        ;

        if (preg_match("/^\s*{$this->opt($this->t('canceled'))}\s*$/iu", $r->getStatus())) {
            $r->general()
                ->cancelled();
        }

        $traveller = $this->re("/\n\s*{$this->opt($this->t('Traveler'))}\n\s*(?:[A-Z]{2} {2,})? +(.+)\n/u", $text);

        if (empty($traveller) && $r->getCancelled()) {
        } else {
            $r->general()->traveller($traveller, true);
        }

        // Price
        if (!$r->getCancelled() && preg_match("/\n\s*{$this->opt($this->t('Total'))} +\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *(?<currency>[A-Z]{3})\n/", $text, $m)) {
            $r->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/\n\s*{$this->opt($this->t('Subtotal'))} +\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *[A-Z]{3}\n/", $text);

            if (!empty($cost)) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $taxesText = array_filter(preg_split("/\n/", $this->re("/\n\s*{$this->opt($this->t('Subtotal'))} +.+\n+([\s\S]+?)\n+\n\s*{$this->opt($this->t('Total'))} {2,}/", $text)));

            foreach ($taxesText as $tt) {
                if (preg_match("/^\s*(\S.+) {3,}\D{0,3} ?(?<total>\d[\d\.\, ]+?) ?\D{0,3} *[A-Z]{3}\s*$/", $tt, $m)) {
                    $r->price()
                        ->fee($m[1], PriceHelper::parse($m[2], $m['currency']));
                }
            }
        } elseif (!$r->getCancelled()) {
            $r->price()
                ->total(null);
        }

        $re = "/^\s*(?<time>\d{1,2}:\d{2}.*)\n\s*(?<date>.+)\n\s*(?<address>.+)\n\s*{$this->opt($this->t('Open hours:'))} *(?<openHours>.+)(?<phone>\n\s*[\d\W]*\d+[\d\W]*)?(?:\s*$|\n\s*)/";
        // $this->logger->debug('$re = '.print_r( $re,true));

        // Pick Up
        $pickUpText = $this->re("/\n\s*{$this->opt($this->t('Pick-up'))}\s*\n([\s\S]+?)\n\s*{$this->opt($this->t('Drop-off'))}\s*\n/u", $text);

        if (preg_match($re, $pickUpText, $m)) {
            $r->pickup()
                ->location($m['address'])
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                ->phone(isset($m['phone']) ? trim($m['phone']) : null, true, true)
                ->openingHours($m['openHours']);
        }

        // Drop Off
        $dropOffText = $this->re("/\n\s*{$this->opt($this->t('Drop-off'))}\s*\n\s*([\s\S]+)/u", $text);

        if (preg_match($re, $dropOffText, $m)) {
            $r->dropoff()
                ->location($m['address'])
                ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                ->phone(isset($m['phone']) ? trim($m['phone']) : null, true, true)
                ->openingHours($m['openHours']);
        }

        // Car
        $r->car()
            ->model($this->re("/\n\s*{$this->opt($this->t('Booking details'))}\s*\n {10,}\S.+\n+ {10,}(\S.+)/u", $text));
        $company = $this->re("/\n\s*{$this->opt($this->t('Booking details'))}\s*\n {10,}(\S.+)\n+ {10,}\S.+/u", $text);

        $provider = $this->getRentalProviderByKeyword($company);

        if (!empty($provider)) {
            $r->setProviderCode($provider);

            $account = $this->re("/\n\s*{$this->opt($this->t('Traveler'))}\n(.*\n+){1,7} {10,}{$this->opt($company)}\s*\n {10,}(.+)/u", $text);

            if (preg_match("/^\s*([A-Z]+[A-Z\s\-]*?) (•{4}[A-Z\d]{4})\s*$/u", $account, $m)) {
                $r->program()
                    ->account($m[2], true, $m[1]);
            }
        } else {
            $r->extra()->company($company);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Navan Inc')]")->length > 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Your rental car is']) && $this->http->XPath->query("//text()[{$this->contains($dict['Your rental car is'])}]")->length > 0
                    && !empty($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0
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
                        if (!empty($dict['Your rental car is']) && $this->strposAll($text, $dict['Your rental car is']) !== false
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            // Di., 11. März 2025, 11:00
            "/^\s*[[:alpha:]]+\.?\s*[,\s]\s*(\d{1,2})\.?\s+([[:alpha:]]+)\.?\s+(\d{4})\s*[\s,]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
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

    private function getRentalProviderByKeyword($keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->detectRentalProviders as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }
}
