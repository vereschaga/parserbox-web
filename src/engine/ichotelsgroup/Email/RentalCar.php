<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalCar extends \TAccountChecker
{
    public $mailFiles = "ichotelsgroup/it-43969537.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Booking Status:'    => ['Booking Status:'],
            'Driver:'            => ['Driver:'],
            'Pricing Summary'    => ['Pricing Summary', 'PRICING SUMMARY'],
            'Supplier & Vehicle' => ['Supplier & Vehicle', 'SUPPLIER & VEHICLE'],
        ],
    ];

    private $subjects = [
        'en' => ['Rental Car Confirmation'],
    ];

    private $detectors = [
        'en' => ['Reservation Summary', 'RESERVATION SUMMARY'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@holidayinnclub.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Holiday Inn Club Vacations') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"holidayinnclub.car.com")]')->length === 0
            && $this->http->XPath->query('//img[contains(@src,"holidayinnclub.com/") and contains(@src,"/logo-1.")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@holidayinnclub.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseCar($email);
        $email->setType('RentalCar' . ucfirst($this->lang));

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

    private function parseCar(Email $email)
    {
        $car = $email->add()->rental();

        $status = $this->http->FindSingleNode("//td[{$this->eq($this->t('Booking Status:'))}]/following-sibling::td[normalize-space()][1]");
        $car->general()->status($status);

        $bookingReference = $this->http->FindSingleNode("//td[{$this->starts($this->t('Booking Reference #:'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($bookingReference) {
            $bookingReferenceTitle = $this->http->FindSingleNode("//td[{$this->starts($this->t('Booking Reference #:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $email->ota()->confirmation($bookingReference, $bookingReferenceTitle);
        }

        $driver = $this->http->FindSingleNode("//td[{$this->eq($this->t('Driver:'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $car->general()->traveller($driver);

        $xpathPricing = "//text()[{$this->eq($this->t('Pricing Summary'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]";

        $totalPaid = $this->http->FindSingleNode($xpathPricing . "/descendant::td[{$this->eq($this->t('Total Paid'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalPaid, $m)) {
            // $198.85
            $car->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($this->normalizeCurrency($m['currency']));

            $m['currency'] = trim($m['currency']);
            $taxes = $this->http->FindSingleNode($xpathPricing . "/descendant::td[{$this->eq($this->t('Fees & Taxes'))}]/following-sibling::td[normalize-space()][1]");

            if (preg_match('/^' . preg_quote($m['currency'], '/') . ' ?(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)) {
                $car->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }

        $datePickup = $this->http->FindSingleNode("//td[{$this->eq($this->t('PICK-UP'))}]/following-sibling::td[normalize-space()][1]");
        $companyPickup = $this->http->FindSingleNode("//td[{$this->eq($this->t('PICK-UP'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[1]");
        $addressPickup = $this->http->FindSingleNode("//td[{$this->eq($this->t('PICK-UP'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[2]");
        $car->pickup()
            ->date2($datePickup)
            ->location($addressPickup);

        $dateDropoff = $this->http->FindSingleNode("//td[{$this->eq($this->t('DROP-OFF'))}]/following-sibling::td[normalize-space()][1]");
        $companyDropoff = $this->http->FindSingleNode("//td[{$this->eq($this->t('DROP-OFF'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[1]");
        $addressDropoff = $this->http->FindSingleNode("//td[{$this->eq($this->t('DROP-OFF'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/td[2]");
        $car->dropoff()
            ->date2($dateDropoff)
            ->location($addressDropoff);

        if ($companyPickup && !$companyDropoff
            || $companyDropoff && !$companyPickup
            || $companyPickup && $companyDropoff && strcasecmp($companyPickup, $companyDropoff) === 0
        ) {
            $car->extra()->company($companyPickup ? $companyPickup : $companyDropoff);
        }

        $xpathVehicle = "//text()[{$this->eq($this->t('Supplier & Vehicle'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::tr[count(*)=2][1]";

        $carImageUrl = $this->http->FindSingleNode($xpathVehicle . "/*[1]/descendant::img[not(contains(@src,'logo'))]/@src");
        $car->car()->image($carImageUrl);

        $carTypeModel = $this->http->FindSingleNode($xpathVehicle . "/*[2]/descendant::text()[normalize-space()][1]");

        if (preg_match("/^(.{2,}?)\s+-\s+(.{2,})$/", $carTypeModel, $m)) {
            // Midsize SUV - Ford Escape or similar
            $car->car()
                ->type($m[1])
                ->model($m[2]);
        }

        $bookingConfirmation = $this->http->FindSingleNode("//td[{$this->starts($this->t('Booking Confirmation #:'))}]/following-sibling::td[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($bookingConfirmation) {
            $bookingConfirmationTitle = $this->http->FindSingleNode("//td[{$this->starts($this->t('Booking Confirmation #:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $car->general()->confirmation($bookingConfirmation, $bookingConfirmationTitle);
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Booking Status:']) || empty($phrases['Driver:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Booking Status:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Driver:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
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
}
