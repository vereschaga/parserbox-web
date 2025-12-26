<?php

namespace AwardWallet\Engine\worldpoints\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Order extends \TAccountChecker
{
    public $mailFiles = "worldpoints/it-136577682.eml, worldpoints/it-140966873.eml, worldpoints/it-140415948.eml, worldpoints/it-141726279.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'              => ['BOOKING REFERENCE'],
            'VEHICLE REGISTRATION'    => ['VEHICLE REGISTRATION', 'REGISTRATION'],
            'dateStart'               => ['ENTRY', 'DROP OFF TIME'],
            'dateEnd'                 => 'EXIT',
            'feeNames'                => ['VAT at 20%'],
            'COLOUR'                  => ['COLOUR', 'COLOR'],
            'Total'                   => ['Total', 'TOTAL'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation of Your Order'],
    ];

    private $detectors = [
        'en' => ['Your booking', 'OFFICIAL PARKING', 'Booking Update'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]heathrow\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".heathrow.com/") or contains(@href,"www.heathrow.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for booking with Heathrow")]')->length === 0
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
        $email->setType('Order' . ucfirst($this->lang));

        $this->parseParking($email);

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

    private function parseParking(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $p = $email->add()->parking();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $p->general()->confirmation($confirmation, $confirmationTitle);
        }

        $patterns['dateTime'] = "(?<date>.{6,}?)\s+{$this->opt($this->t('at'))}\s+(?<time>{$patterns['time']})";

        $dateOfOrder = $this->http->FindSingleNode("//text()[{$this->eq($this->t('DATE OF ORDER'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]")
            ?? implode(' ', $this->http->FindNodes("//*[{$this->eq($this->t('DATE OF ORDER'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^{$patterns['dateTime']}$/", $dateOfOrder, $m)) {
            $p->general()->date(strtotime($m['time'], strtotime($m['date'])));
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $p->general()->traveller($traveller);
        }

        $xpathFilter = "not(preceding::*[{$this->eq($this->t('OLD BOOKING'))}])"; // it-140966873.eml, it-141726279.eml
        $xpathTable = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('TERMINAL'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('VEHICLE REGISTRATION'))} or {$this->eq($this->t('dateStart'))}] ][{$xpathFilter}]";
        $dop = "/ancestor-or-self::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('TERMINAL'))}]]";

        $location = $this->http->FindSingleNode($xpathTable . $dop . "/preceding-sibling::*[normalize-space()][1]/descendant-or-self::*[count(*[normalize-space()])=2][1]/*[normalize-space()][1]");
        $p->place()->location($location);

        $terminal = $this->http->FindSingleNode($xpathTable . "/*[normalize-space()][1]", null, true, "/^\s*{$this->opt($this->t('TERMINAL'))}\s*(.+)/");
        $p->place()->address(!empty($terminal) ? 'Heathrow Airport, ' . $terminal : $terminal);

        $plate = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('VEHICLE REGISTRATION'))}] ][$xpathFilter]/*[normalize-space()][2]");

        if (empty($plate)) {
            $plate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('VEHICLE REGISTRATION'))}]/following::text()[normalize-space()][1]");
        }

        $vehicleDetails = $this->http->XPath->query("//*[ *[normalize-space()][1][{$this->eq($this->t('VEHICLE DETAILS'))}] and *[normalize-space()][2] ]");

        if ($vehicleDetails->length === 1) {
            // it-141726279.eml, it-140415948.eml
            $rootVehicle = $vehicleDetails->item(0);
//            $plate = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('REGISTRATION'))}]/following-sibling::*[normalize-space()]", $rootVehicle);
            $plate = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('REGISTRATION'))}]/ancestor::*[not({$this->eq($this->t('REGISTRATION'))})][1]", $rootVehicle, true, "/^\s*{$this->opt($this->t('REGISTRATION'))}\s*(.+)/");

            $carDescriptionParts = [];
            $carDescriptionParts[] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('COLOUR'))}]/ancestor::*[not({$this->eq($this->t('COLOUR'))})][1]", $rootVehicle, true, "/^\s*{$this->opt($this->t('COLOUR'))}\s*(.+)/");
            $carDescriptionParts[] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('MAKE'))}]/ancestor::*[not({$this->eq($this->t('MAKE'))})][1]", $rootVehicle, true, "/^\s*{$this->opt($this->t('MAKE'))}\s*(.+)/");
            $carDescriptionParts[] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('MODEL'))}]/ancestor::*[not({$this->eq($this->t('MODEL'))})][1]", $rootVehicle, true, "/^\s*{$this->opt($this->t('MODEL'))}\s*(.+)/");
            $carDescription = implode(' ', array_filter($carDescriptionParts));
            $p->booked()->car($carDescription);
        }
        $p->booked()->plate($plate);

        $dateStart = $this->http->FindSingleNode($xpathTable . "/following-sibling::*[normalize-space()][1]/descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('dateStart'))}] ]/*[normalize-space()][2]")
            ?? $this->http->FindSingleNode($xpathTable . "/descendant::*[ count(.//text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('dateStart'))}] ]/descendant::text()[normalize-space()][2]") // it-140415948.eml
            ?? $this->http->FindSingleNode($xpathTable . "/following::tr[1]/descendant::*[ count(.//text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('dateStart'))}] ]/descendant::text()[normalize-space()][2]")
        ;

        if (preg_match("/^{$patterns['dateTime']}$/", $dateStart, $m)) {
            $p->booked()->start(strtotime($m['time'], strtotime($m['date'])));
        }

        $dateEnd = $this->http->FindSingleNode($xpathTable . $dop . "/following-sibling::*[normalize-space()][1]/descendant::*[ count(.//text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('dateEnd'))}] ]/descendant::text()[normalize-space()][2]");

        if (preg_match("/^{$patterns['dateTime']}$/", $dateEnd, $m)) {
            $p->booked()->end(strtotime($m['time'], strtotime($m['date'])));
        }

        $totalPrice = $this->http->FindSingleNode("//*[ count(.//text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/descendant::text()[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // £123.60
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $p->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Net total (excluding VAT)'))}] ]/*[normalize-space()][2]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $p->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $p->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
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
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dateStart'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['dateStart'])}]")->length > 0
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
