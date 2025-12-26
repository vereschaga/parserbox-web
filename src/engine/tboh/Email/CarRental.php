<?php

namespace AwardWallet\Engine\tboh\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "tboh/it-550876258.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'company'      => ['Car rental supplier:', 'Car rental supplier :'],
            'confNumber'   => ['Supplier confirmation No:', 'Supplier confirmation No :'],
            'cancellation' => [
                'Cancellation Policy', 'Cancellation Policy (Time in UTC)',
                'Cancelation Policy', 'Cancelation Policy (Time in UTC)',
            ],
        ],
    ];

    private $subjects = [
        'en' => ['Car Rental Voucher For Confirmation No'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@tboholidays.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(.,"@tboholidays.com")]')->length === 0
        ) {
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
        $email->setType('CarRental' . ucfirst($this->lang));

        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $car = $email->add()->rental();

        $status = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Booking status:'))}]", null, true, "/^{$this->opt($this->t('Booking status:'))}[:\s]*([^:]+)$/i");
        $car->general()->status($status);

        $otaConfirmation = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Booking reference number:'))}]");

        if (preg_match("/^({$this->opt($this->t('Booking reference number:'))})[:\s]*([-A-z\d]{5,})$/i", $otaConfirmation, $m)) {
            $car->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $company = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('company'))}]", null, true, "/^{$this->opt($this->t('company'))}[:\s]*([^:]+)$/i");

        if (($code = self::normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $confirmation = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-z\d]{5,})$/i", $confirmation, $m)) {
            $car->general()->confirmation($m[2], rtrim($m[1], ': '));
        } elseif ($this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('company'))}]/following-sibling::*[{$this->starts($this->t('confNumber'))}]", null, true, "/^{$this->opt($this->t('confNumber'))}$/") !== null) {
            $car->general()->noConfirmation();
        }

        $traveller = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");

        $car->general()->traveller($traveller, true);

        $datePickUp = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ][ following::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]", null, true, "/^.{3,}\b\d{4}$/"));
        $locationPickUp = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Location:'))}] ][ following::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]");
        $phonePickUp = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Telephone:'))}] ][ following::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['phone']}$/u");

        $dateDropOff = strtotime($this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Date:'))}] ][ preceding::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]", null, true, "/^.{3,}\b\d{4}$/"));
        $locationDropOff = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Location:'))}] ][ preceding::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]");
        $phoneDropOff = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Telephone:'))}] ][ preceding::tr[{$this->eq($this->t('Drop off'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['phone']}$/u");

        $car->pickup()->date($datePickUp)->location($locationPickUp)->phone($phonePickUp, false, true);
        $car->dropoff()->date($dateDropOff)->location($locationDropOff)->phone($phoneDropOff, false, true);

        $carImg = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Car Category:'))}]/preceding-sibling::tr[descendant::img[@src] and normalize-space()=''][1]/descendant::img/@src");
        $carModel = implode(' ', $this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('Car Category:'))}]/preceding-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space() and not(ancestor::small)]"));
        $carCategory = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Car Category:'))}]", null, true, "/^{$this->opt($this->t('Car Category:'))}[:\s]*(.{2,})$/");
        $car->car()->image($carImg)->model($carModel)->type($carCategory);

        $cancellation = implode(' • ', $this->http->FindNodes("//tr[{$this->eq($this->t('cancellation'))}]/following-sibling::tr[normalize-space()]"));
        $car->general()->cancellation($cancellation);

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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    public static function normalizeProvider(?string $string): ?string
    {
        // used in tboh/CarRental2

        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'national'     => ['National'],
            'sixt'         => ['Sixt'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['company']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['company'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
