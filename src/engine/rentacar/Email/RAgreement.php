<?php

namespace AwardWallet\Engine\rentacar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RAgreement extends \TAccountChecker
{
    public $mailFiles = "rentacar/it-36016115.eml, rentacar/it-36231597.eml, rentacar/it-46176564.eml, rentacar/it-5190986.eml, rentacar/it-5256467.eml, rentacar/it-59554256.eml, rentacar/it-59606140.eml, rentacar/it-59671170.eml";

    public $reSubject = [
        'Enterprise Rental Agreement',
        'NATIONAL Rental Agreement',
        'ALAMO Rental Agreement',
    ];
    public $reBody = [
        ["RA #", "Nº RA"],
    ];

    public $langDetect = [
        "en" => "Renter",
        "de" => "Mieter",
        "es" => "Arrendatario",
        "fr" => "Locataire",
    ];

    public static $dictionary = [
        "en" => [
            "Confirmation" => "RA #",
            //"Renter" => "",
            //"Pickup" => "",
            "Return" => ["Return", "Intended Return"],
            //"Vehicle" => "",
            "Total Charges:" => "Total Charges:",
            //"Make/Model" => "",
            //"Charges" => "",
            //"Total" => "",
        ],

        "de" => [
            "Confirmation"   => "RA #",
            "Renter"         => "Mieter",
            "Pickup"         => "Pickup",
            "Return"         => "Rückgabe",
            "Vehicle"        => "Fahrzeug",
            "Total Charges:" => "Abzgl. Rechnungsstellung äóñ - ENTERPRISE PLUS REWARDS:",
            "Make/Model"     => "Marke/Modell",
            "Charges"        => "Kosten",
            "Total"          => "Gesamt",
        ],

        "es" => [
            "Confirmation"   => "RA #",
            "Renter"         => "Arrendatario",
            "Pickup"         => "Pickup",
            "Return"         => "Devoluci—n",
            "Vehicle"        => "Veh’culo",
            "Total Charges:" => "Menos Facturar a äóñ - SPAIN WEB PRE PAY:",
            "Make/Model"     => "Marca/Modelo",
            "Charges"        => "Cargos",
            "Total"          => "Total",
        ],

        "fr" => [
            "Confirmation"   => "Nº RA",
            "Renter"         => "Locataire",
            "Pickup"         => "Prise en charge",
            "Return"         => "Retour",
            "Vehicle"        => "Véhicule",
            "Make/Model"     => "Marque/Modéle",
            "Charges"        => "Frais",
            "Total"          => "Total",
            "Total Charges:" => "Total des frais:",
        ],
    ];

    private $providerCode = '';

    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->assignProvider($parser->getHeaders());

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re[0]) !== false || stripos($body, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@enterprise.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailProviders()
    {
        return ['rentacar', 'national', 'alamo'];
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]", null, true, "/{$this->opt($this->t('Confirmation'))}\s*:\s*([A-Z\d]{5,})$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{5,})$/");
        }

        if (!empty($number)) {
            $r->general()
                ->confirmation($number);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Renter'))}]", null, true, "#{$this->opt($this->t('Renter'))}\s*:\s*(.+)#");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller);
        }

        $datePickup = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Pickup'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Pickup'))}\s*(.{6,})/"));

        if (!empty($datePickup)) {
            $r->pickup()
                ->date(strtotime($datePickup));
        }

        // +377 (93) 15 48 52    |    713.680.2992
        $patterns['phone'] = '/^[+(\d][-. \d)(]{5,}[\d)]$/';

        $pickupLocationTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Pickup'))}]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]");

        if (count($pickupLocationTexts) > 1
            && preg_match($patterns['phone'], $pickupLocationTexts[count($pickupLocationTexts) - 1])
        ) {
            $r->pickup()
                ->location(implode(' ', array_slice($pickupLocationTexts, 0, -1)))
                ->phone($pickupLocationTexts[count($pickupLocationTexts) - 1]);
        } else {
            $r->pickup()
                ->location(implode(' ', $pickupLocationTexts));
        }

        $dateDropoff = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Return'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Return'))}\s*(.{6,})/"));

        if (!empty($dateDropoff)) {
            $r->dropoff()
                ->date(strtotime($dateDropoff));
        }

        $dropoffLocationTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Return'))}]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]");

        if (count($dropoffLocationTexts) > 1
            && preg_match($patterns['phone'], $dropoffLocationTexts[count($dropoffLocationTexts) - 1])
        ) {
            $r->dropoff()
                ->location(implode(' ', array_slice($dropoffLocationTexts, 0, -1)))
                ->phone($dropoffLocationTexts[count($dropoffLocationTexts) - 1]);
        } else {
            $r->dropoff()
                ->location(implode(' ', $dropoffLocationTexts));
        }

        $carModel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle'))}]/ancestor-or-self::tr[1]/following::tr[normalize-space()][1]/td[normalize-space()][1]", null, true, "#{$this->opt($this->t('Make/Model'))}\s*:\s*(.+)#");

        if (empty($carModel)) {
            $carModel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle'))}]/ancestor::tr[1]/following::tr[1]/td[1]");
        }

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        $totalPayment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Charges:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d\s]*)/', $totalPayment, $m) || preg_match('/^(?<amount>\d[,.\'\d\s]*)[ ]*(?<currency>[^\d)(]+?)/u', $totalPayment, $m)) {
            // $127.40 || 127.40 $
            $r->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));

            //$feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][1]/descendant::text()[{$this->eq($this->t('Charges'))}] and *[normalize-space()][3]/descendant::text()[{$this->eq($this->t('Total'))}] ]/following::tr[normalize-space()]");
            $feeRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Charges'))}]/ancestor::tr[1]/following::tr/td[3]/ancestor::tr");

            foreach ($feeRows as $fee) {
                if ($this->http->XPath->query("*/descendant::text()[{$this->starts($this->t('Total Charges:'))}]", $fee)->length > 0) {
                    break;
                }
                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $fee);
                $feeCharge = $this->http->FindSingleNode('*[position()>2][normalize-space()][last()]', $fee);

                if ($feeName !== null && preg_match('/^\(?' . preg_quote($m['currency'], '/') . '[ ]*(?<amount>\d[,.\'\d\s]*)/', $feeCharge, $matches) || preg_match('/^(?<amount>\d[,.\'\d\s]*)[ ]*\(?' . preg_quote($m['currency'], '/') . '/', $feeCharge, $matches)) {
                    $feeAmount = $this->normalizeAmount($matches['amount']);

                    if (strcasecmp($feeName, 'DISCOUNT') === 0) {
                        // ($40.16)*
                        $r->price()
                            ->discount($feeAmount);

                        continue;
                    }
                    $r->price()
                        ->fee($feeName, $feeAmount);
                }
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach ($this->langDetect as $lang => $option) {
            if (strpos($this->http->Response["body"], $option) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.', 'Rs'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function assignProvider($headers): bool
    {
        if (self::detectEmailFromProvider($headers['from']) === true
            || stripos($headers['subject'], 'Enterprise Rental Agreement') !== false
            || $this->http->XPath->query('//img[contains(@src,".enterprise.com") and contains(@src,"/ban_enterpriseLogo.")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"Enterprise Rental Agreement")]')->length > 0
        ) {
            $this->providerCode = 'rentacar';

            return true;
        }

        if (stripos($headers['subject'], 'NATIONAL Rental Agreement') !== false
            || $this->http->XPath->query('//img[contains(@alt,"National Banner Logo")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"NATIONAL Rental Agreement")]')->length > 0
        ) {
            $this->providerCode = 'national';

            return true;
        }

        if (stripos($headers['subject'], 'ALAMO Rental Agreement') !== false
            || $this->http->XPath->query('//img[contains(@alt,"Alamo Banner Logo")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"ALAMO Rental Agreement")]')->length > 0
        ) {
            $this->providerCode = 'alamo';

            return true;
        }

        return false;
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            // Dec 13, 2016 7:32 AM
            '/^([[:alpha:]]+)[-,.\s\/]+(\d{1,2})[-,.\s\/]+(\d{2,4})[-,.\s\/]+(\d{1,2}[:]+\d{2}(?:\s*[AaPp][Mm])?)$/u',
            // 31 May, 2019 17:41
            '/^(\d{1,2})[-,.\s\/]+([[:alpha:]]+)[-,.\s\/]+(\d{2,4})[-,.\s\/]+(\d{1,2}[:]+\d{2}(?:\s*[AaPp][Mm])?)$/u',
            // 18 may, 20199:22
            '/^(\d+)\s+(\w+)\,\s+(\d{4})([\d\:]+)$/',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
