<?php

namespace AwardWallet\Engine\tboh\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CarRental2 extends \TAccountChecker
{
    public $mailFiles = "tboh/it-564965116.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'dropOff'         => ['Drop-off'],
            'otaConfNumber'   => ['Confirmation No:', 'Confirmation No :'],
            'typeVariants'    => ['Intermediate', 'Standard', 'Compact'],
            'cancellation'    => [
                'Cancellation Policy', 'Cancellation Policy (Time in UTC)',
                'Cancelation Policy', 'Cancelation Policy (Time in UTC)',
            ],
        ],
    ];

    private $subjects = [
        'en' => ['Car Rental Booking Details For Confirmation No'],
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
        $email->setType('CarRental2' . ucfirst($this->lang));

        $patterns = [
            'date'          => '[-[:alpha:]]+\s*,\s*\d{1,2}[-\s]+[[:alpha:]]+[-\s]+\d{4}', // Mon,09 Oct 2023
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $rootNodes = $this->http->XPath->query("//*[ tr[normalize-space()][1][{$this->eq($this->t('Car Booking Details'))}] ]/ancestor::table[1]");
        $this->logger->debug('Found ' . $rootNodes->length . ' root-nodes.');
        $root = $rootNodes->length > 0 ? $rootNodes->item(0) : null;

        $car = $email->add()->rental();

        $pickUpVal = $this->http->FindSingleNode("descendant::tr/*[{$this->starts($this->t('Pick-up'))}]", $root, true, "/^{$this->opt($this->t('Pick-up'))}[:\s]*(.+)$/");
        $dropOffVal = $this->http->FindSingleNode("descendant::tr/*[{$this->starts($this->t('Drop-off'))}]", $root, true, "/^{$this->opt($this->t('Drop-off'))}[:\s]*(.+)$/");

        if (preg_match($pattern = "/^(?<location>.{3,}?)\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})/u", $pickUpVal, $m)) {
            $car->pickup()->location($m['location'])->date(strtotime($m['time'], strtotime($m['date'])));
        }

        if (preg_match($pattern, $dropOffVal, $m)) {
            $car->dropoff()->location($m['location'])->date(strtotime($m['time'], strtotime($m['date'])));
        }

        $otaConfirmation = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and {$this->starts($this->t('otaConfNumber'))}]", $root);

        if (preg_match("/^({$this->opt($this->t('otaConfNumber'))})[:\s]*([-A-z\d]{5,})$/i", $otaConfirmation, $m)) {
            $car->ota()->confirmation($m[2], rtrim($m[1], ': '));
            $car->general()->noConfirmation();
        }

        $status = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and {$this->starts($this->t('Status:'))}]", $root, true, "/^{$this->opt($this->t('Status:'))}[:\s]*(.{2,})$/");
        $car->general()->status($status);

        $xpathExtra = "descendant::*[ count(table)=2 and table[1][descendant::img] and table[2][{$this->contains($this->t('or similar'))}] ]";

        $carImg = $this->http->FindSingleNode($xpathExtra . "/table[1]/descendant::img[contains(normalize-space(@alt),'Car Image') or contains(@src,'vehicle')]/@src", $root);

        $car->car()->image($carImg, false, true);

        $carDetails = implode("\n", $this->http->FindNodes($xpathExtra . "/table[2]/descendant-or-self::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]", $root));

        if (preg_match("/^(.{2,}{$this->opt($this->t('or similar'))})\n/", $carDetails, $m)) {
            $car->car()->model($m[1]);
        }

        if (preg_match("/^({$this->opt($this->t('typeVariants'))})$/im", $carDetails, $m)) {
            $car->car()->type($m[1]);
        }

        $travellerParts = [];

        $firstName = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and {$this->starts($this->t('First Name:'))}]", $root, true, "/^{$this->opt($this->t('First Name:'))}[:\s]*({$patterns['travellerName']})$/u");

        if ($firstName) {
            $travellerParts[] = $firstName;
        }

        $lastName = $this->http->FindSingleNode("descendant::tr/*[not(.//tr) and {$this->starts($this->t('Last Name:'))}]", $root, true, "/^{$this->opt($this->t('Last Name:'))}[:\s]*({$patterns['travellerName']})$/u");

        if ($lastName) {
            $travellerParts[] = $lastName;
        }

        $car->general()->traveller(implode(' ', $travellerParts), true);

        $company = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Supplier Details'))}]/following::tr[normalize-space()][1]/descendant::text()[ancestor::*[{$xpathBold}] and {$this->eq($this->t('Name'))}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", $root);

        if (($code = CarRental::normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        if (!empty($car->getPickUpLocation())) {
            $locationPickUpVariants = [$car->getPickUpLocation(), strtoupper($car->getPickUpLocation())];
            $addressPickUp = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Supplier Details'))}]/following::tr[normalize-space()][1]/descendant::text()[ancestor::*[{$xpathBold}] and {$this->eq($locationPickUpVariants)}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", $root);

            if ($addressPickUp) {
                $car->pickup()->location($addressPickUp);
            }
        }

        if (!empty($car->getDropOffLocation())) {
            $locationDropOffVariants = [$car->getDropOffLocation(), strtoupper($car->getDropOffLocation())];
            $addressDropOff = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Supplier Details'))}]/following::tr[normalize-space()][1]/descendant::text()[ancestor::*[{$xpathBold}] and {$this->eq($locationDropOffVariants)}]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", $root);

            if ($addressDropOff) {
                $car->dropoff()->location($addressDropOff);
            }
        }

        $cancellation = implode(' • ', $this->http->FindNodes("descendant::tr[{$this->eq($this->t('cancellation'))}]/following-sibling::tr[normalize-space()]", $root));
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['dropOff']) || empty($phrases['otaConfNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->starts($phrases['dropOff'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length > 0
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
