<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TollReceipt extends \TAccountChecker
{
    public $mailFiles = "hertz/it-35785639.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Rental Start Date:' => ['Rental Start Date:'],
            'Return Location:'   => ['Return Location:'],
            'Summary of Charges' => ['Summary of Charges', 'Summary of PlatePass Charges'],
            'chargesNames'       => ['Toll Charges:', 'Convenience Fee:'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'Hertz Toll Receipt') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for renting with Hertz")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseCar($email);
        $email->setType('TollReceipt' . ucfirst($this->lang));

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

        $lastName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Last Name:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Last Name:'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u");
        $car->general()->traveller($lastName, false);

        $startDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rental Start Date:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Rental Start Date:'))}\s*(.{6,})/");
        $car->pickup()->date2($startDate);

        $pickupLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pickup Location:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Pickup Location:'))}\s*(.{3,})$/");
        $car->pickup()->location($pickupLocation);

        $endDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rental End Date:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Rental End Date:'))}\s*(.{6,})/");
        $car->dropoff()->date2($endDate);

        $returnLocation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Return Location:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Return Location:'))}\s*(.{3,})$/");
        $car->dropoff()->location($returnLocation);

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Summary of Charges'))}]/following::text()[{$this->eq($this->t('Total:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/', $total, $m)) {
            // $33.30
            $car->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']))
            ;
            $charges = $this->http->FindNodes("//text()[ preceding::text()[{$this->eq($this->t('Summary of Charges'))}] and following::text()[{$this->eq($this->t('Total:'))}] ][{$this->starts($this->t('chargesNames'))}]");

            foreach ($charges as $charge) {
                if (preg_match('/^(?<name>[^:]+?)\s*:\s*' . preg_quote($m['currency'], '/') . '[ ]*(?<amount>\d[,.\'\d]*)$/', $charge, $matches)) {
                    $car->price()->fee($matches['name'], $this->normalizeAmount($matches['amount']));
                }
            }
        }

        $car->general()->noConfirmation();
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Rental Start Date:']) || empty($phrases['Return Location:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Rental Start Date:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Return Location:'])}]")->length > 0
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

    private function starts($field, $node = '.'): string
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

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

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
}
