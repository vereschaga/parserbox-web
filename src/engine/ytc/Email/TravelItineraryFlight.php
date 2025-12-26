<?php

namespace AwardWallet\Engine\ytc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelItineraryFlight extends \TAccountChecker
{
    public $mailFiles = "ytc/it-152351435.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'flightNumber'   => ['Flight Number'],
            'arrival'        => ['Arrival'],
            'statusPhrases'  => 'your booking is',
            'statusVariants' => 'confirmed',
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation Flight Booking'],
    ];

    private $detectors = [
        'en' => [
            'Travel Itinerary detail', 'Travel itinerary Detail', 'Travel Itinerary Detail', 'Travel itinerary detail',
            'Passenger Details', 'Passenger details',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@web-fares.com') !== false;
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("//tr[normalize-space()='' and count(descendant::img[contains(@src,'ytc.wfares.com') and contains(@src,'/logo')])=1]")->length === 0
            && $this->http->XPath->query('//*[contains(.,"@web-fares.com")]')->length === 0
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
        $email->setType('TravelItinerary' . ucfirst($this->lang));

        $f = $email->add()->flight();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;!?]|$)/");
        $f->general()->status($status);

        $xpathFHeader = "//tr[ *[1][{$this->eq($this->t('Departure'))}] and *[2][{$this->eq($this->t('arrival'))}] and *[3][{$this->eq($this->t('Cabin'))}] ]";

        $pnrCell = $this->http->FindSingleNode($xpathFHeader . "/preceding::tr[normalize-space()][1]/*[normalize-space()][2]");

        if (preg_match("/^({$this->opt($this->t('PNR'))})[:\s]*([A-Z\d]{5,})$/", $pnrCell, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $s = $f->addSegment();

        $airportDep = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][1]/*[1]");
        $codeDep = $this->re("/^.{2,}\(\s*([A-Z]{3})\s*\)(?:\s*,|$)/", $airportDep);
        $dateDep = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][2]/*[1]", null, true, '/^.*\d.*$/');
        $terminalDep = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][3]/*[1]", null, true, "/^TERMINAL\s*([A-Z\d][A-Z\d ]*)$/i");
        $s->departure()
            ->name($airportDep)
            ->code($codeDep)
            ->date2($dateDep)
            ->terminal($terminalDep, false, true)
        ;

        $airportArr = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][1]/*[2]");
        $codeArr = $this->re("/^.{2,}\(\s*([A-Z]{3})\s*\)(?:\s*,|$)/", $airportArr);
        $dateArr = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][2]/*[2]", null, true, '/^.*\d.*$/');
        $terminalArr = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][3]/*[2]", null, true, "/^TERMINAL\s*([A-Z\d][A-Z\d ]*)$/i");
        $s->arrival()
            ->name($airportArr)
            ->code($codeArr)
            ->date2($dateArr)
            ->terminal($terminalArr, false, true)
        ;

        $cabin = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][1]/*[3]");
        $s->extra()->cabin($cabin);

        $flightVal = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][4]/*[1]");

        if (preg_match("/^{$this->opt($this->t('flightNumber'))}[:\s]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[- ]*(?<number>\d+)$/", $flightVal, $m)) {
            $s->airline()->name($m['name'])->number($m['number']);
        }

        $extraVal = $this->http->FindSingleNode($xpathFHeader . "/following-sibling::tr[normalize-space()][4]/*[2]");

        if (preg_match("/{$this->opt($this->t('EquipmentType'))}[:\s]+(.{2,}?)\s+{$this->opt($this->t('Duration'))}/", $extraVal, $m)) {
            $s->extra()->aircraft($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('Duration'))}[:\s]+(\d[.:\d ]*)$/", $extraVal, $m)) {
            $s->extra()->duration($m[1]);
        }

        $passengerRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[ *[1][{$this->eq($this->t('Passenger Name'))}] and *[5][{$this->eq($this->t('Seat'))}] ] and following-sibling::tr[{$this->starts($this->t('Fare Details'))}] ]");

        foreach ($passengerRows as $pRow) {
            $traveller = $this->http->FindSingleNode("*[1]", $pRow, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
            $f->general()->traveller($traveller, true);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ *[4][{$this->eq($this->t('Total Fare'))}] ]/following-sibling::tr[normalize-space()][1]/*[4]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // USD 237.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('BaseFare'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $tax = $this->http->FindSingleNode("//tr[ *[2][{$this->eq($this->t('Tax'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $tax, $m)) {
                $taxTitle = $this->http->FindSingleNode("//tr[ following-sibling::tr[normalize-space()][1]/*[2] ]/*[2][{$this->eq($this->t('Tax'))}]");
                $f->price()->fee($taxTitle, PriceHelper::parse($m['amount'], $currencyCode));
            }

            $serviceTax = $this->http->FindSingleNode("//tr[ *[3][{$this->eq($this->t('Service Tax'))}] ]/following-sibling::tr[normalize-space()][1]/*[3]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $serviceTax, $m)) {
                $serviceTaxTitle = $this->http->FindSingleNode("//tr[ following-sibling::tr[normalize-space()][1]/*[3] ]/*[3][{$this->eq($this->t('Service Tax'))}]");
                $f->price()->fee($serviceTaxTitle, PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

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
            if (!is_string($lang) || empty($phrases['flightNumber']) || empty($phrases['arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['flightNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['arrival'])}]")->length > 0
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }
}
