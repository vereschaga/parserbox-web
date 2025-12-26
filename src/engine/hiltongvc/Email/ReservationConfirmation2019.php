<?php

namespace AwardWallet\Engine\hiltongvc\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation2019 extends \TAccountChecker
{
    public $mailFiles = "hiltongvc/it-269155953.eml, hiltongvc/it-42600079.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'    => ['ARRIVAL:', 'ARRIVAL :'],
            'checkOut'   => ['DEPARTURE:', 'DEPARTURE :'],
            'confNumber' => ['CONFIRMATION NUMBER:', 'CONFIRMATION NUMBER :'],
        ],
    ];

    private $detectors = [
        'en' => ['Here are your package details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Hilton Grand Vacations') !== false
            || preg_match('/[-.@]hiltongrandvacations\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/\bHGVC\s*Reservation\s*Confirmation/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Hilton Grand Vacations is pleased to be your host") or contains(normalize-space(),"The Hilton Grand Vacations Team")]')->length === 0) {
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

        $this->parseHotel($email);
        $email->setType('ReservationConfirmation2019' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $hotelName = $address = $phone = null;
        $addressRows = $this->http->FindNodes("//td[ descendant::text()[{$this->starts($this->t('checkIn'))}] and descendant::text()[{$this->starts($this->t('confNumber'))}] ]/ancestor::tr[count(*[normalize-space()])=2]/*[normalize-space()][1]/descendant::*[count(p[normalize-space()])>1]/p[normalize-space()]");
        $this->logger->debug(var_export($addressRows, true));

        if (count($addressRows) > 1) {
            $hotelName = array_shift($addressRows);

            if (preg_match("/^[+(\d][-. \d)(]{5,}[\d)]$/", end($addressRows))) {
                $phone = array_pop($addressRows);
            }
            $address = implode(' ', $addressRows);

            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone, false, true);
        } elseif ($hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('checkIn'))}]/ancestor::table[1]/preceding::table[1]/descendant::text()[normalize-space()]")) {
            $h->hotel()
                ->name($hotelName)
                ->noAddress();
        }

        $h->booked()
            ->checkIn2($this->http->FindSingleNode("//text()[{$this->starts($this->t('checkIn'))}]/following::text()[normalize-space()][1]"))
            ->checkOut2($this->http->FindSingleNode("//text()[{$this->starts($this->t('checkOut'))}]/following::text()[normalize-space()][1]"));

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathPackage = "//text()[{$this->starts($this->t('Here are your package details'))}]";

        $points = $this->http->FindSingleNode($xpathPackage . "/following::text()[{$this->contains($this->t('PLUS'))} and {$this->contains($this->t('Point'))}]", null, true, "/{$this->opt($this->t('PLUS'))}\s*(\d.+?{$this->opt($this->t('Point'))})/");
        $h->program()->earnedAwards($points, false, true);

        $totalPaid = $this->http->FindSingleNode($xpathPackage . "/following::text()[{$this->eq($this->t('Total Paid:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Total Paid:'))}[:\s]*(.+)/");

        if (preg_match('/^(?<currency>[^\d)(]+) ?(?<amount>\d[,.\'\d]*)$/', $totalPaid, $m)) {
            // $223.88
            $h->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']));
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
            if (!is_string($lang) || empty($phrases['checkOut']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['checkOut'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
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
