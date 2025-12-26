<?php

// bcdtravel + screenshot in dropbox
// Beware, maybe this is what you need It3321802 !!!

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingHtml2017 extends \TAccountChecker
{
    public $mailFiles = ""; // +5 bcdtravel(html)[sv,fr,da,nl,fr]

    private $lang = '';

    private $subjects = [
        'sv' => 'Min bokning hos Hertz',
        'de' => 'Meine Hertz Reservierung',
        'nl' => 'Mijn Hertz Reservering',
        'fr' => 'Ma réservation Hertz',
        'da' => 'Min Hertz billeje bestilling',
        //'es' => 'Confirmación de tu reserva',
    ];

    private $body = [
        'sv' => ['Tack för att du reser med Hertz.'],
        'de' => ['Vielen Dank für Ihre Reservierung'],
        'nl' => ['Dank u voor uw reservering bij Hertz,'],
        'fr' => ['Merci d\'avoir choisi Hertz'],
        'da' => ['Tak fordi du valgte at bestille din billeje hos Hertz'],
        //'es' => ['Gracias por viajar a la velocidad de'],
    ];

    private static $dict = [
        'sv' => [
            'Vad du betalar nu' => ['Totalt', 'Vad du betalar nu'],
        ],
        'de' => [
            'Ditt bokningsnummer är:'   => 'Reservierungsnummer lautet:',
            'Tack för att du reser med' => 'Vielen Dank für Ihre Reservierung',
            'Adress'                    => 'Adresse',
            'Upphämtningstid'           => 'Anmietung',
            'Återlämningstid'           => 'Rückgabe',
            'Öppettider'                => 'Öffnungszeiten',
            'Telefonnummer'             => 'Telefonnummer',
            'Faxnummer'                 => 'Fax Nummer',
            'Vad du betalar nu'         => 'Später zahlen',
            'Ditt fordon'               => 'Ihr Fahrzeug',
        ],
        'fr' => [
            'Ditt bokningsnummer är:'   => 'Votre numéro de confirmation est:',
            'Tack för att du reser med' => 'Merci d\'avoir choisi Hertz',
            'Adress'                    => 'Adresse',
            'Telefonnummer'             => 'Téléphone:',
            'Faxnummer'                 => 'Fax ::',
            'Upphämtningstid'           => 'Date de départ',
            'Återlämningstid'           => 'Date de retour',
            'Öppettider'                => 'Horaires d\'ouverture:',
            'Vad du betalar nu'         => 'Payer à l‘agence',
            'Ditt fordon'               => 'Votre véhicule',
        ],
        'nl' => [
            'Ditt bokningsnummer är:'   => 'Uw bevestigingsnummer is:',
            'Tack för att du reser med' => 'Dank u voor uw reservering bij Hertz,',
            'Adress'                    => 'Addres',
            'Telefonnummer'             => 'Telefoonnummer:',
            'Faxnummer'                 => 'Faxnummer:',
            'Upphämtningstid'           => 'Ophaalgegevens',
            'Återlämningstid'           => 'Inlevergegevens',
            'Öppettider'                => 'Openingstijden:',
            'Vad du betalar nu'         => 'Betalen op locatie',
            'Ditt fordon'               => 'uw voertuig:',
        ],
        'da' => [
            'Ditt bokningsnummer är:'   => 'Dit bestillingsnr. er:',
            'Tack för att du reser med' => 'Tak fordi du valgte at bestille din billeje hos Hertz',
            'Adress'                    => 'Adresse',
            'Telefonnummer'             => 'Telefon::',
            'Faxnummer'                 => 'Fax::',
            'Upphämtningstid'           => ['Afhentning:', 'Afhentning :'],
            'Återlämningstid'           => 'Returnering',
            'Öppettider'                => 'Åbningstider',
            'Vad du betalar nu'         => 'Betal ved afhentning',
            'Ditt fordon'               => 'Din bil',
        ],
        //        'es' => [
        //            'Ditt bokningsnummer är:' => 'Tu número de confirmación es el siguiente:',
        //            'Tack för att du reser med' => 'Gracias por viajar a la velocidad de',
        //            'Adress' => 'Localidad de recolección',
        //            'Upphämtningstid' => 'Anmietung',
        //            'Återlämningstid' => 'Rückgabe',
        //            'Öppettider' => 'Öffnungszeiten',
        //            'Telefonnummer' => 'Telefonnummer',
        //            'Faxnummer' => 'Fax Nummer',
        //            'Vad du betalar nu' => 'Später zahlen',
        //            'Ditt fordon' => 'Ihr Fahrzeug',
        //        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false && !preg_match('/\bHertz\b/', $headers['subject'])) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->subjects) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'hertz') !== false
            && $this->arrikey($parser->getHTMLBody(), $this->body) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'hertz.') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->lang = $this->arrikey($parser->getHTMLBody(), $this->body)) {
            foreach ($this->http->XPath->query("//text()[starts-with(normalize-space(.), '{$this->t('Ditt bokningsnummer är:')}')]/ancestor::table[contains(normalize-space(.), '{$this->t('Adress')}')][1]") as $root) {
                $its[] = $this->parseCar($root);
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookningHtml2017' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseCar($root)
    {
        $result = ['Kind' => 'L'];
        $result['Number'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('Ditt bokningsnummer är:')}')]/following::text()[normalize-space(.)][1]", null, true, '/^([A-z\d]{5,})$/');
        $name = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"' . $this->t("Tack för att du reser med") . '")]');

        if (preg_match('/\.?\s*([A-Z\s]+)$/', $name, $matches)) {
            $result['RenterName'] = $matches[1];
        }

        // Location
        $result['PickupLocation'] = join(', ', array_filter($this->http->FindNodes("(.//text()[starts-with(normalize-space(.), '{$this->t('Adress')}')]/ancestor::td[1])[1]", $root, "/{$this->t('Adress')}\s*:*(.+)/")));
        $result['DropoffLocation'] = join(', ', array_filter($this->http->FindNodes("(.//text()[starts-with(normalize-space(.), '{$this->t('Adress')}')]/ancestor::td[1])[2]", $root, "/{$this->t('Adress')}\s*:*(.+)/")));

        if (empty($result['DropoffLocation'])) {
            $result['DropoffLocation'] = $result['PickupLocation'];
        }

        // Date
        $pickupDatetime = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Upphämtningstid'))}]/ancestor::td[1]", $root, false, "/{$this->opt($this->t('Upphämtningstid'))}\s*:*(.+)/");
        $result['PickupDatetime'] = strtotime($this->dateNormalize($pickupDatetime, $this->lang), false);
        $dropoffDatetime = $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Återlämningstid'))}]/ancestor::td[1])[last()]", $root, false, "/{$this->opt($this->t('Återlämningstid'))}\s*:*(.+)/");
        $result['DropoffDatetime'] = strtotime($this->dateNormalize($dropoffDatetime, $this->lang), false);

        // Hours Job
        $result['PickupHours'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Öppettider')}')])[1]/ancestor::td[1]", $root, false, "/{$this->t('Öppettider')}\s*:*\s*(.+)/");
        $result['DropoffHours'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Öppettider')}')])[2]/ancestor::td[1]", $root, false, "/{$this->t('Öppettider')}\s*:*\s*(.+)/");

        if (empty($result['DropoffHours'])) {
            $result['DropoffHours'] = $result['PickupHours'];
        }

        // Phone, Fax
        $result['PickupPhone'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Telefonnummer')}')])[1]/ancestor::td[1]", $root, false, '/[+\d\s()-]{7,}/');
        $result['PickupFax'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Faxnummer')}')])[1]/ancestor::td[1]", $root, false, '/[+\d\s()-]{7,}/');
        $result['DropoffPhone'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Telefonnummer')}')])[2]/ancestor::td[1]", $root, false, '/[+\d\s()-]{7,}/');
        $result['DropoffFax'] = $this->http->FindSingleNode("(.//text()[contains(normalize-space(.),'{$this->t('Faxnummer')}')])[2]/ancestor::td[1]", $root, false, '/[+\d\s()-]{7,}/');

        if (empty($result['DropoffPhone'])) {
            $result['DropoffPhone'] = $result['PickupPhone'];
        }

        if (empty($result['DropoffFax'])) {
            $result['DropoffFax'] = $result['PickupFax'];
        }

        // Total, Currency
        $total = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Vad du betalar nu'))}]/ancestor::td[1])[1]");

        if (preg_match('/\b[A-Z]{3}\b/', $total, $matches)) {
            $result['TotalCharge'] = preg_replace('/[^\d.]+/', '', $total);
            $result['Currency'] = $matches[0];
            $fees = $this->http->XPath->query("//tr[starts-with(normalize-space(.), 'Betala på plats') and not(.//tr)]/preceding-sibling::tr[1]/descendant::tr[count(td)=2][not(contains(normalize-space(.), 'WINTERIZED FEE'))]/td[1]");
            /** @var \DOMNode $fee */
            foreach ($fees as $fee) {
                $result['Fees'][] = [
                    'Name'   => trim($fee->nodeValue),
                    'Charge' => $this->http->FindSingleNode('following-sibling::td[normalize-space(.)][1]', $fee),
                ];
            }
        }

        // Car
        $result['CarImageUrl'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vad du betalar nu'))}]/ancestor::tr[1]/following-sibling::tr[1]//img/@src[not(contains(.,'cid:'))]");

        if (empty($result['CarImageUrl'])) {
            $result['CarImageUrl'] = $this->http->FindSingleNode("//img[contains(@src, 'vehicles')][1]/@src");
        }
        $result['CarType'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'{$this->t('Ditt fordon')}')]/ancestor::tr[1]/following-sibling::tr[1])[1]");
        $result['CarModel'] = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'{$this->t('Ditt fordon')}')]/ancestor::tr[1]/following-sibling::tr[2])[1]/td");

        return $result;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * TODO: In php problems with "Type declarations", so i did so.
     * Are case sensitive. Example:
     * <pre>
     * var $reBody = ['en' => ['Reservation Modify'],];
     * var $reSubject = ['Reservation Modify']
     * </pre>.
     *
     * @param string $haystack
     *
     * @return int, string, false
     */
    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function dateNormalize($subject, $lang)
    {
        $pattern = [
            '/^\s*\w+\.?, (\d+) (\w+)\.?, (\d+) \w+ (\d+:\d+)$/u', // to, 27 apr, 2017 för 22:30
        ];
        $replacement = [
            '$1 $2 $3, $4',
        ];
        $replace = preg_replace($pattern, $replacement, trim($subject));

        return $this->dateTranslate('/\d+ ([[:alpha:]]+) \d+, .+/u', $replace, $lang);
    }

    private function dateTranslate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        } else {
            return $string;
        }
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        if (!is_array($field)) {
            $field = (array) $field;
        }

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
