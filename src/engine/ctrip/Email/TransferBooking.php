<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TransferBooking extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-761160070.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Booking Number'],
            'From'         => ['From'],
            'To'           => ['To'],
            'Pick-up Time' => ['Pick-up Time'],
            'cancellation' => ['Cancell and refund policy', 'Cancel and refund policy'],
        ],
    ];

    private $subjects = [
        'en' => ['Airport Transfer Booking - Driver Confirmed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || strpos($headers['subject'], '[Trip.com]') === false)
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".trip.com/") or contains(@href,"www.trip.com") or contains(@href,"uk.trip.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Copyright ©") and contains(normalize-space(),"Trip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Trip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('TransferBooking' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }

        $root = $roots->item(0);

        $patterns = [
            'time'           => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
        ];

        $otaConfirmation = $otaConfirmationTitle = null;
        $otaConfirmationVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[1]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([-A-Z\d]{4,40})(?:\s*\(|$)/", $otaConfirmationVal, $m)) {
            $otaConfirmation = $m[2];
            $otaConfirmationTitle = $m[1];
        }

        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $t = $email->add()->transfer();

        if ($otaConfirmation) {
            $t->general()->noConfirmation();
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // HK$ 679.30
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $s = $t->addSegment();

        $locationDep = implode(', ', $this->http->FindNodes("*[normalize-space()][1][{$this->starts($this->t('From'))}]/descendant::text()[normalize-space()]", $root));
        $locationArr = implode(', ', $this->http->FindNodes("*[normalize-space()][2][{$this->starts($this->t('To'))}]/descendant::text()[normalize-space()]", $root));

        $s->departure()->name(preg_replace("/^{$this->opt($this->t('From'))}[:\s,]+(.*)$/i", '$1', $locationDep));
        $s->arrival()->name(preg_replace("/^{$this->opt($this->t('To'))}[:\s,]+(.*)$/i", '$1', $locationArr));

        $datePickUp = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up Time'), "translate(.,':','')")}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<date>.{4,}?\b\d{4})[,\s]+(?<time>{$patterns['time']})/", $datePickUp, $m)) {
            // 15 Oct, 2024 15:30
            $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            $s->arrival()->noDate();
        }

        $passengersVal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'), "translate(.,':','')")}]/following::text()[normalize-space()][1]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $passengersVal, $m)) {
            $s->extra()->adults($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/i", $passengersVal, $m)) {
            $s->extra()->kids($m[1]);
        }

        $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle Type'), "translate(.,':','')")} and not(preceding::*[{$this->eq($this->t('Service Providers Details'), "translate(.,':','')")}])]/following::text()[normalize-space()][1]", null, true, "/^.+[^:]$/");
        $carModel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Vehicle Type'), "translate(.,':','')")} and preceding::*[{$this->eq($this->t('Service Providers Details'), "translate(.,':','')")}]]/following::text()[normalize-space()][1]", null, true, "/^\D+[^:]$/");
        $s->extra()->type($carType, false, true)->model($carModel, false, true);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('cancellation'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^.+[^:]$/");
        $t->general()->cancellation($cancellation, false, true);

        $traveller = $this->http->FindSingleNode("//*[{$this->eq($this->t('Passenger Details'), "translate(.,':','')")}]/following::text()[{$this->eq($this->t('Name'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", null, true, "/^(?:{$patterns['travellerName']}|{$patterns['travellerName2']})$/u");
        $t->general()->traveller($traveller, true);

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

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('From'))}] and *[normalize-space()][2][{$this->starts($this->t('To'))}] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick-up Time'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Pick-up Time'])}]")->length > 0) {
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'HKD' => ['HK$'],
            'SGD' => ['S$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
