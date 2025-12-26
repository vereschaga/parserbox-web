<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3242130 extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-13478406.eml, rentacar/it-13478523.eml, rentacar/it-3242130.eml, rentacar/it-3293930.eml, rentacar/it-3313405.eml, rentacar/it-3313610.eml, rentacar/it-3315808.eml, rentacar/it-3315830.eml, rentacar/it-3315913.eml, rentacar/it-3319836.eml, rentacar/it-3849422.eml, rentacar/it-85534591.eml, rentacar/it-95310708.eml, rentacar/it-95317996.eml";

    public $lang = '';

    public static $dict = [
        'fr' => [ // it-95310708.eml, it-95317996.eml
            'confNo'              => 'Votre numéro de réservation est',
            'confNo-re'           => 'Votre numéro de réservation est(?: le)?',
            'YOUR RESERVATION IS' => 'VOTRE RÉSERVATION EST',
            'Pick-Up Details'     => ['Détails du rendez-vous', 'Informations concernant le retrait de votre véhicule'],
            'Date & Time'         => ['Date et heure:', 'Date et heure :', 'Date et heure'],
            'Location'            => ['Emplacement:', 'Emplacement :', 'Emplacement', 'Agence'],
            'Return Details'      => ['Détails du retour', 'Informations concernant la restitution'],
            'Phone'               => ['Numéro De Téléphone', 'Téléphone'],
            'Hours'               => ['Heures', 'Horaires'],
            'Pricing Details'     => ['Détails du prix', 'Détails tarifaires'],
            'Vehicle Class'       => 'Catégorie de véhicule',
            'Renter Details'      => ['Détails du locataire', 'Informations concernant le locataire'],
            'Name'                => 'Nom',
            'Estimated Total'     => ['Total Estimé', 'Coût Total Estimé'],
            'Taxes & Fees'        => 'Taxes et frais',
            'Address'             => 'Adresse',

            'Your reservation' => 'Votre réservation',
            'was cancelled'    => 'a été annulée',
        ],
        'de' => [
            'confNo'    => ['Ihre Reservierungsnummer lautet', 'Die Bestätigungsnummer lautet'],
            'confNo-re' => '(?:Ihre Reservierungsnummer lautet|Die Bestätigungsnummer lautet)',
            'YOUR RESERVATION IS' => 'RESERVIERUNG IST',
            'Pick-Up Details' => 'Details zur Abholung',
            'Date & Time'     => 'Datum und Uhrzeit',
            'Location'        => 'Station',
            'Return Details'  => ['Details zur Rückgabe', 'Rückgabedaten'],
            'Phone'           => ['Telefonnummer', 'Telefon'],
            'Hours'           => ['Öffnungszeiten', 'Geschäftszeiten'],
            'Pricing Details' => ['Details zum Preis', 'Preisangaben'],
            'Vehicle Class'   => ['FAHRZEUGKLASSE', 'Fahrzeugklasse'],
            'Renter Details'  => ['DETAILS ZUM MIETER', 'Daten des Mieters'],
            'Name'            => 'Name',
            'Estimated Total' => ['Voraussichtliche Gesamtkosten', 'Voraussichtliche Gesamtsumme'],
            'Taxes & Fees'    => 'Steuern und Gebühren',
            'Address'         => 'Adresse',

            //            'Your reservation' => '',
            //            'was cancelled' => '',
        ],
        'sv' => [
            'confNo'    => 'Ditt bekräftelsenummer är',
            'confNo-re' => 'Ditt bekräftelsenummer är',
            //            'YOUR RESERVATION IS' => '',
            'Pick-Up Details' => 'Hämtningsinformation',
            'Date & Time'     => 'Datum och tid',
            'Location'        => 'Plats',
            'Return Details'  => 'Om återlämningen',
            'Phone'           => 'Telefon',
            'Hours'           => 'Timmar',
            'Pricing Details' => 'Prisinformation',
            'Vehicle Class'   => 'Fordonsklass',
            'Renter Details'  => 'Information om hyrestagare',
            'Name'            => 'Namn',
            'Estimated Total' => 'Uppskattad summa',
            'Taxes & Fees'    => 'Skatter och avgifter',
            'Address'         => 'Adress',

            //            'Your reservation' => '',
            //            'was cancelled' => '',
        ],
        'en' => [
            'confNo' => [
                "The confirmation number is",
                "Your confirmation number is",
                "The reservation number is",
                "Your reservation number is",
            ],
            'confNo-re'           => '(?:Your|The) (?:confirmation|reservation) number is',
            'YOUR RESERVATION IS' => ['YOUR RESERVATION IS', 'RESERVATION IS'],
            'Pick-Up Details'     => 'Pick-Up Details',
            'Location'            => ['Location', 'Location:'],
            'Date & Time'         => ['Date & Time', 'Date & Time:'],
            'Name'                => ['Name:', 'Name'],
            'Renter Details'      => ['Renter Details', 'Driver Details'],
            'Estimated Total'     => ['Estimated Total', 'Total'],
            'Taxes & Fees'        => ['Taxes & Fees', 'Taxes and Fees'],
            //            'Your reservation' => '',
            'was cancelled' => ['was cancelled', 'was canceled'],
        ],
        'es' => [ // it-85534591.eml
            'confNo'              => ['Número de localizador:', 'Tu número de confirmación es', 'El número de confirmación es', 'El número de reserva es', 'Tu número de reservación es'],
            'confNo-re'           => '(?:Número de localizador:|Tu número de confirmación es|El número de confirmación es|El número de reserva es|Tu número de reservación es)',
            'YOUR RESERVATION IS' => ['SU RESERVACIÓN ESTÁ', 'TU RESERVA SE', 'RESERVACIÓN', 'RESERVA'],
            'Pick-Up Details'     => ['Información de recogida', 'Información de Entrega', 'Detalles de la recogida'],
            'Date & Time'         => ['Fecha y hora', 'Fecha & Hora'],
            'Location'            => 'Oficina',
            'Return Details'      => ['Información de devolución', 'Información de Devolución', 'Detalles de la devolución'],
            'Phone'               => 'Teléfono',
            'Hours'               => 'Horas',
            'Pricing Details'     => ['Detalles del precio', 'Detalle de Auto & Tarifas'],
            'Vehicle Class'       => ['Tipo de vehículo', 'Clase de Vehículo', 'Clase de vehículo'],
            'Renter Details'      => ['Detalles del arrendatario', 'Información Conductor'],
            'Name'                => 'Nombre',
            'Estimated Total'     => ['Total Estimado', 'Total Garantizado'],
            'Taxes & Fees'        => ['Impuestos y tasas', 'Impuestos y tarifas', 'Impuestos'],
            'Address'             => 'Dirección',
            //            'Your reservation' => '',
            //            'was cancelled' => '',
        ],
    ];

    private $detectSubject = [
        'fr' => 'Réservation Enterprise Rent-A-Car',
        'de' => 'Enterprise Rent-A-Car-Reservierung',
        'es' => 'Reserva de Enterprise Rent-A-Car',
        'en' => 'Enterprise Rent-A-Car Reservation', 'Enterprise Rent-A-Car-bokning',
    ];

    private $detectProvider = ["Rent A Car", "Rent-A-Car"];

    private $detectors = [
        'fr' => ['VOTRE RÉSERVATION EST CONFIRMÉE', ' a été annulée.'],
        'de' => ['IHRE RESERVIERUNG WURDE BESTÄTIGT', 'RESERVIERUNG IST BESTÄTIGT'],
        'sv' => 'BOKNINGEN HAR BEKRÄFTATS.',
        'en' => ['YOUR RESERVATION IS CONFIRMED', 'RESERVATION IS CONFIRMED', 'YOUR RESERV ATION IS CONFIRMED', 'RESERVATION HAS BEEN MODIFIED', 'Thank you for your reservation.', ' was cancelled.', ' was canceled.'],
        'es' => ['SU RESERVACIÓN ESTÁ CONFIRMADA', 'RESERVACIÓN CONFIRMADA', 'TU RESERVA SE MODIFICÓ', 'RESERVA CONFIRMADA'],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]enterprise\.(?:com|ca|de)$/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $detectedProvider = false;

        foreach ($this->detectProvider as $p) {
            if (false !== stripos($body, $p) || $this->http->XPath->query("//node()[{$this->contains($p)}]")->length > 0) {
                $detectedProvider = true;
            }
        }

        if ($detectedProvider == false) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], 'Enterprise Rent-A-Car') === false)
        ) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $r = $email->add()->rental();

        // General
        $confNumberText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[not(.//tr) and {$this->contains($this->t('confNo'))}]"));

        if (preg_match("/({$this->t('confNo-re')})[:\s]*([-A-Z\d]{5,35})(?:\s*[,.:;!?]|$)/", $confNumberText, $m)) {
            $r->general()->confirmation($m[2], rtrim($m[1], ': '));
        } else {
            $num = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation'))} and {$this->contains($this->t('was cancelled'))}]", null, true, "/{$this->opt($this->t('Your reservation'))}\s+([A-Z\d]{5,})\s+{$this->opt($this->t('was cancelled'))}/");
            $r->general()->confirmation($num);
        }

        $r->general()->traveller($this->getField($this->t("Renter Details"), $this->t("Name")));

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('YOUR RESERVATION IS'))}]",
            null, true, "/{$this->opt($this->t('YOUR RESERVATION IS'))}\s+(.+?)[!.\s]*$/");

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation'))} and {$this->contains($this->t('was cancelled'))}]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        if ($status !== null) {
            $r->general()->status($status);
        }

        // Progmam
        $account = $this->getField($this->t("Membership"), $this->t("Membership Number"));

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        // Pick Up
        $r->pickup()
            ->date($this->normalizeDate($this->getField($this->t("Pick-Up Details"), $this->t("Date & Time"))))
            ->location($this->getField($this->t("Pick-Up Details"), $this->t("Location")) . ', ' . $this->getField($this->t("Pick-Up Details"), $this->t("Address")))
            ->phone($this->getField($this->t("Pick-Up Details"), $this->t("Phone")), true, true)
            ->openingHours($this->getField($this->t("Pick-Up Details"), $this->t("Hours")), true, true)
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDate($this->getField($this->t("Return Details"), $this->t("Date & Time"))))
            ->location($this->getField($this->t("Return Details"), $this->t("Location")) . ', ' . $this->getField($this->t("Return Details"), $this->t("Address")))
            ->phone($this->getField($this->t("Return Details"), $this->t("Phone")), true, true)
            ->openingHours($this->getField($this->t("Return Details"), $this->t("Hours")), true, true)
        ;

        // Car
        $xpathVehicle = "//*[not(.//tr) and {$this->eq($this->t('Pricing Details'))}]/following::table[normalize-space()][1]//tr/*[1][{$this->eq($this->t('Vehicle Class'))}]/following-sibling::*[normalize-space()][1]";
        $vehicle = $this->htmlToText($this->http->FindHTMLByXpath($xpathVehicle));
        $vehicleParts = array_filter(preg_split('/\s*\n+\s*/', $vehicle));

        if (count($vehicleParts) === 2) {
            // it-13478406.eml
            if ($this->http->XPath->query($xpathVehicle . "/descendant::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] or ancestor::span[1][{$this->eq($vehicleParts[0])}] ]")->length > 0) {
                $r->car()->type($vehicleParts[0]);
            }
            $r->car()->model($vehicleParts[1]);
        } elseif (count($vehicleParts) === 1) {
            $r->car()->model($vehicleParts[0]);
        }

        // Price
        $total = null;

        foreach ((array) $this->t('Estimated Total') as $phrase) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($phrase)}]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]", null, true, '/^.*\d.*$/');

            if ($total) {
                break;
            }
        }

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            // €313.90
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['curr']) ? $m['curr'] : null;
            $r->price()->currency($m['curr'])->total(PriceHelper::parse($m['amount'], $currencyCode));
        }

        // Fees
        $xpath = "//text()[{$this->eq($this->t('Taxes & Fees'))}]/ancestor::*[1]/following::table[normalize-space()][1]/descendant::tr[count(*[normalize-space()])=2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $charge = $this->http->FindSingleNode('*[normalize-space()][last()]', $root, true, '/^\D*(\d[\d ,.]*)\D*$/');

            if ($charge !== null) {
                $name = $this->http->FindSingleNode('*[normalize-space()][1]', $root);
                $r->price()->fee($name, PriceHelper::parse($charge));
            }
        }

        // Discount
        $savings = [];
        $savingsRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Savings'))}]/following::table[1]//tr");

        foreach ($savingsRows as $sRow) {
            $charge = $this->http->FindSingleNode('td[last()]', $sRow, true, '/^\D*(\d[\d ,.]*)\D*$/');

            if ($charge !== null) {
                $savings[] = PriceHelper::parse($charge);
            }
        }

        if (count($savings)) {
            $r->price()->discount(array_sum($savings));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dict, $this->lang)) {
            return false;
        }

        foreach (self::$dict as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick-Up Details']) || empty($phrases['Date & Time'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Pick-Up Details'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Date & Time'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
        // $this->logger->notice($date);
        $in = [
            // Wednesday, March 17, 2021 @ 6:00 PM
            "/^[-[:alpha:]]+[,.\s]+([[:alpha:]]+)\s+(\d{1,2})[,.\s]+(\d{2,4})\s*[,@]\s*({$this->patterns['time']}).*$/u",
            // lördag, augusti 24, 2019, 4:30 em [sv]
            "/^[-[:alpha:]]+[,.\s]+([[:alpha:]]+)\s+(\d{1,2})[,.\s]+(\d{2,4})\s*[,@]\s*(\d{1,2}:\d{2}\s*[ef]m\b).*$/iu",
            // Mie, 14 de Jul, 2021 @ 12:00 PM    |    viernes, 10 de junio de 2022 @ 13:30
            "/^[-[:alpha:]]+[,.\s]+(\d{1,2})\.?\s+(?:de\s+)?([[:alpha:]]+)[,.\s]+(?:de\s+)?(\d{2,4})\s*[,@]\s*({$this->patterns['time']}).*$/u",
            // 03 October 2021 @ 08:00
            "/^(\d{1,2}\s*[[:alpha:]]+\s*\d{4})[\s@]+({$this->patterns['time']}).*$/u",
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            '$1, $2',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->error($date);

        if ($this->lang == 'sv') {
            $date = preg_replace(['/\s+em$/i', '/\s+fm$/i'], [' pm', ' am'], $date);
        }

        if ('en' !== $this->lang && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function getField($section, $field, $child = "."): ?string
    {
        $xpath = "//text()[{$this->eq($section)}]/ancestor::*[1]/following::table[1]//tr/td[1][{$this->eq($field)}]/following-sibling::td[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            return $this->http->FindSingleNode($child, $root);
        }

        return null;
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
