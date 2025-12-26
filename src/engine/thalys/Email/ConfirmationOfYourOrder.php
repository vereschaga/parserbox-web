<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationOfYourOrder extends \TAccountChecker
{
    public $mailFiles = "thalys/it-223750131.eml, thalys/it-227655522.eml, thalys/it-259764772.eml, thalys/it-394883312.eml, thalys/it-395139537.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your confirmation' => ['Your confirmation', 'YOUR NEW BOOKING', 'Your cancellation', 'YOUR CANCELLATION'],
            'cancelledTicket'   => ['We confirm the cancellation of your reservation', 'Your cancelled reservation'],
            //            'PNR :' => '',
            //            'Train No.' => '',
            'Duration :' => 'Duration :',
            //            'Car' => '',
            //            'Place' => '',
            'ACCESS MY RESERVATION' => 'ACCESS MY RESERVATION',
            // 'Payment overview' => '',
            // 'Total amount:' => '',
            //            'Reservation number ' => '',
            // 'Payment summary' => '', // exchange ticket
            // 'Price of the new tickets' => '', // exchange ticket
        ],
        'fr' => [
            'Your confirmation'     => ['Votre confirmation', 'nouvelle réservation', 'Votre annulation'],
            'cancelledTicket'       => ['Nous vous confirmons l’annulation de votre réservation', 'Votre réservation annulée'],
            'PNR :'                 => 'PNR :',
            'Train No.'             => 'Train N°',
            'Duration :'            => 'Durée :',
            'Car'                   => 'Voiture',
            'Place'                 => 'Place',
            'ACCESS MY RESERVATION' => ['ACCÉDER À MA RÉSERVATION', 'Gérer ma réservation'],
            'Payment overview'      => 'Récapitulatif de paiement',
            'Reservation number '   => 'Réservation N°',
            'Total amount:'         => ['Montant total :', 'Montant payé  :'],
            // 'Payment summary' => '', // exchange ticket
            // 'Price of the new tickets' => '', // exchange ticket
        ],
        'de' => [
            'Your confirmation'     => 'Ihre Buchungsbestätigung',
            // 'cancelledTicket' => ['', ''],
            'PNR :'                 => 'PNR :',
            'Train No.'             => 'Zug Nr.',
            'Duration :'            => 'Reisedauer :',
            'Car'                   => 'Wagen',
            'Place'                 => 'Platz',
            'ACCESS MY RESERVATION' => 'ZU MEINER TICKETBUCHUNG',
            'Payment overview'      => 'Zusammenfassung der Zahlung',
            'Reservation number '   => 'Buchungsnr',
            'Total amount:'         => 'Gesamtbetrag:',
            // 'Payment summary' => '', // exchange ticket
            // 'Price of the new tickets' => '', // exchange ticket
        ],
        'nl' => [
            'Your confirmation'     => 'Uw bevestiging',
            // 'cancelledTicket' => ['', ''],
            'PNR :'                 => 'PNR :',
            'Train No.'             => 'Trein nr.',
            'Duration :'            => 'Duur :',
            'Car'                   => 'Rijtuig',
            'Place'                 => 'Zitplaats',
            'ACCESS MY RESERVATION' => 'NAAR MIJN BOEKING',
            'Payment overview'      => 'Betalingsoverzicht',
            'Reservation number '   => 'Boekingsnummer ',
            'Total amount:'         => 'Totaal bedrag:',
            // 'Payment summary' => '', // exchange ticket
            // 'Price of the new tickets' => '', // exchange ticket
        ],
    ];

    private $detectFrom = 'noreply@thalys.com';
    private $detectSubject = [
        // en
        'Confirmation of your order',
        'Confirmation of your exchange',
        'Confirmation of your cancellation',
        // fr
        'Confirmation de votre commande',
        'Confirmation de votre annulation',
        // de
        'Bestätigung Ihrer Bestellung',
        // nl
        'Bevestiging van uw bestelling',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.thalys.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your confirmation'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Your confirmation'])}]")->length > 0
                && (!empty($dict['ACCESS MY RESERVATION'])
                    && $this->http->XPath->query("//a[{$this->contains($dict['ACCESS MY RESERVATION'])} and contains(@href, 'thalys')] | //img[{$this->contains($dict['ACCESS MY RESERVATION'], '@alt')}]")->length > 0
                    || !empty($dict['cancelledTicket'])
                    && $this->http->XPath->query("//node()[{$this->contains($dict['cancelledTicket'])}]")->length > 0
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Duration :"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Duration :'])}]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        // Price All
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Payment overview"))}]/following::td[not(.//td)][{$this->starts($this->t("Total amount:"))}]",
            null, true, "/:(.+)/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment summary'))}]/following::text()[{$this->eq($this->t("Price of the new tickets"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                null, true, "/(.+)/");
        }

        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $m['curr']))
                ->currency($m['curr'])
            ;
        }

        $xpath = "//text()[{$this->starts($this->t('Duration :'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $trainsByPNR = [];

        foreach ($nodes as $root) {
            $pnr = $this->http->FindSingleNode("./preceding::tr[normalize-space()][3]", $root, null,
                "/{$this->opt($this->t("PNR :"))}\s*([A-Z\d]{5,})\s*/su");
            $trainsByPNR[$pnr][] = $root;
        }

        // Price by segment
        $pricesAll = $this->http->FindNodes("//text()[{$this->eq($this->t("Payment overview"))}]/following::td[not(.//td)][{$this->starts($this->t("Reservation number "))}]/following-sibling::td[normalize-space()][1]");

        if (count($pricesAll) === count($trainsByPNR)) {
            $prices = $pricesAll;
        }

        foreach ($trainsByPNR as $pnr => $roots) {
            $t = $email->add()->train();

            $t->general()
                ->confirmation($pnr);

            if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledTicket'))}]")->length > 0) {
                $t->general()
                    ->status('Cancelled')
                    ->cancelled();
            }

            // price

            // Segments

            $travellers = [];

            foreach ($roots as $root) {
                $s = $t->addSegment();

                $date = $this->http->FindSingleNode("preceding::tr[normalize-space()][2]/td[1]", $root, true,
                    "/\|\s*(.+)/");

                // Departure
                $s->departure()
                    ->date($this->normalizeDate($date . ',' . $this->http->FindSingleNode("preceding::tr[normalize-space()][1]/td[1]", $root)))
                    ->name($this->http->FindSingleNode("preceding::tr[normalize-space()][1]/td[2]", $root))
                    ->geoTip('Europe')
                ;

                // Arraival
                $s->arrival()
                    ->date($this->normalizeDate($date . ',' . $this->http->FindSingleNode("following::tr[normalize-space()][1]/td[1]", $root)))
                    ->name($this->http->FindSingleNode("following::tr[normalize-space()][1]/td[2]", $root))
                    ->geoTip('Europe')
                ;

                $s->extra()
                    ->number($this->http->FindSingleNode("preceding::tr[normalize-space()][2]/td[2]", $root, true,
                    "/{$this->opt($this->t("Train No."))}\s*(\d+)\s*$/"))
                    ->duration($this->http->FindSingleNode(".", $root, true,
                    "/{$this->opt($this->t("Duration :"))}\s*(.+)\s*$/"))
                ;
                // Seats
                $segBeforeCount = 1 + count($this->http->FindNodes("preceding::text()[{$this->starts($this->t('Duration :'))}]", $root));
                $segAfterCount = count($this->http->FindNodes("following::text()[{$this->starts($this->t('Duration :'))}]", $root));

                if ($segAfterCount + $segBeforeCount == $nodes->length) {
                    $addXpath = "following::tr[{$this->starts($this->t("Car"))} and {$this->contains($this->t("Place"))}]"
                        . "[count(preceding::text()[{$this->starts($this->t('Duration :'))}]) = {$segBeforeCount} and count(following::text()[{$this->starts($this->t('Duration :'))}]) = {$segAfterCount}]";
                    $travellers += $this->http->FindNodes($addXpath . "/preceding::tr[normalize-space()][1]", $root);
                    $s->extra()
                        ->car($this->http->FindSingleNode("({$addXpath})[1]", $root, true, "/{$this->opt($this->t("Car"))}\s*(\w+)\b/"))
                        ->seats($this->http->FindNodes($addXpath, $root, "/{$this->opt($this->t("Place"))}\s*(\w+)\b/"))
                    ;
                } else {
                    $t->general()
                        ->travellers([]);
                }
            }

            $t->general()
                ->travellers($travellers, true);

            // Price
            if (!empty($prices)) {
                $total = array_shift($prices);

                if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
                    || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)
                    || preg_match("/^(?<amount>[\d\.\,])\s*(?<currency>\D{1,3})$/", $total, $m)) {
                    $t->price()
                        ->total(PriceHelper::parse($m['amount'], $m['curr']))
                        ->currency($m['curr'])
                    ;
                }
            }
        }

        if (count($pricesAll) == 0 && $this->http->XPath->query("//text()[normalize-space()='Montant des nouveaux billets']")->length > 0) {
            $t->price()
                ->cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Montant des nouveaux billets'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Montant des nouveaux billets'))}\s*([\d\.\,]+)/"))
                ->tax($this->http->FindSingleNode("//text()[{$this->starts($this->t('Retenues et frais'))}]/ancestor::tr[1]", null, true, "/\s([\d\.\,]+)/su"))
                ->total($this->http->FindSingleNode("//text()[{$this->starts($this->t('Montant payé'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Montant payé'))}\s*\:?\s*([\d\.\,]+)/"));

            $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Montant payé'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Montant payé'))}\s*\:?\s*[\d\.\,]+(\D)$/ui");
            $t->price()
                ->currency($this->normalizeCurrency($currency));

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Montant des billets échangés'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Montant des billets échangés'))}\s+\‑([\d\.\,]+)/ui");

            if (!empty($discount)) {
                $t->price()
                    ->discount($discount);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (
            // Monday 02 January 2023,09:22
            preg_match("/^\s*[[:alpha:]]+\s+(?<d>\d{1,2})\s+(?<m>[[:alpha:]]+)\s+(?<y>\d{4})\s*,\s*(?<time>\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui", $date, $m)
        ) {
            $date = $m['d'] . ' ' . $m['m'] . ' ' . $m['y'] . ', ' . $m['time'];

//            $this->logger->debug('date end = ' . print_r( $date, true));

            if (preg_match("/^\s*\d+\s+([[:alpha:]]{3,})\s+\d{4}\b/u", $date, $mat)) {
                if ($en = MonthTranslate::translate($mat[1], $this->lang)) {
                    $date = str_replace($mat[1], $en, $date);
                }
            }

            return strtotime($date);
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
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
}
