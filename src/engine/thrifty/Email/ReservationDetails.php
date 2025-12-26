<?php

namespace AwardWallet\Engine\thrifty\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "thrifty/it-19313342.eml";

    private $langDetectors = [
        'en' => ['Drop off Location:'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'feesTitles' => ['Days', 'Airport or Ferry Terminal service charge'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Thrifty Reservation') !== false
            || stripos($from, '@thrifty.co.nz') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'Thrifty Reservation') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for booking with Thrifty") or contains(.,"www.thrifty.co.nz") or contains(.,"@thrifty.co.nz")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.thrifty.co.nz")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->assignLang() === false) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('ReservationDetails' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        ];

        $r = $email->add()->rental();

        // travellers
        $traveller = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Dear ')) . ']', null, true, '/^' . $this->opt($this->t('Dear ')) . '\s*(.+)(?:[,.!]|$)/m'); // Dear

        if ($traveller) {
            $r->addTraveller($traveller);
        }

        // confirmationNumber
        $confNumberText = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Your Confirmation Number')) . ']');

        if (preg_match('/(' . $this->opt($this->t('Your Confirmation Number')) . ')[-\s]+([A-Z\d]{5,})\b/', $confNumberText, $matches)) {
            $r->general()->confirmation($matches[2], $matches[1]);
        }

        $xpathFragment1 = '/ancestor::td[ ./following-sibling::td ][1]/following-sibling::td[normalize-space(.)][1]';

        // carType
        $r->car()->type($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Vehicle type:')) . ']' . $xpathFragment1));

        // pickUpLocation
        $r->pickup()->location($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Pickup Location:')) . ']' . $xpathFragment1));

        // pickUpDateTime
        $r->pickup()->date2($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Pick up Date:')) . ']' . $xpathFragment1));
        $timePickUp = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Pick up Time:')) . ']' . $xpathFragment1, null, true, '/(' . $patterns['time'] . ')/');

        if ($r->getPickUpDateTime() && $timePickUp) {
            $r->pickup()->date(strtotime($timePickUp, $r->getPickUpDateTime()));
        }

        // dropOffLocation
        $r->dropoff()->location($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Drop off Location:')) . ']' . $xpathFragment1));

        // dropOffDateTime
        $r->dropoff()->date2($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Drop off Date:')) . ']' . $xpathFragment1));
        $timeDropOff = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Drop off Time:')) . ']' . $xpathFragment1, null, true, '/(' . $patterns['time'] . ')/');

        if ($r->getDropOffDateTime() && $timeDropOff) {
            $r->dropoff()->date(strtotime($timeDropOff, $r->getDropOffDateTime()));
        }

        // p.currencyCode
        // p.total
        $payment = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Total price includes')) . ']' . $xpathFragment1);

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)$/', $payment, $matches)) {
            $r->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
            // p.fees
            $feeNodes = $this->http->XPath->query('//text()[' . $this->eq($this->t('Cost Details')) . ']/following::text()[' . $this->eq($this->t('feesTitles')) . ']');

            foreach ($feeNodes as $feeNode) {
                $feeCharge = $this->http->FindSingleNode('./' . $xpathFragment1, $feeNode);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.\'\d]*)$/', $feeCharge, $m)) {
                    $r->price()->fee($feeNode->nodeValue, $m['amount']);
                }
            }
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00   ->  18800.00

        return $string;
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
            'NZD' => ['NZ', 'NZ$'],
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
