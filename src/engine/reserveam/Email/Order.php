<?php

namespace AwardWallet\Engine\reserveam\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Order extends \TAccountChecker
{
    public $mailFiles = "reserveam/it-593009603.eml, reserveam/it-600945297-cancelled.eml, reserveam/it-862030195.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['Reservation Number:', 'Reservation Number :'],
            'checkIn'         => ['Check In'],
            'statusVariants'  => ['Confirmed', 'Cancelled', 'Canceled'],
            'cancelledStatus' => ['Cancelled', 'Canceled'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation Letter Email'],
    ];

    private $detectors = [
        'en' => ['Site #'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@reserveamerica.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".reserveamerica.com/")]')->length === 0
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

        $patterns = [
            'date'          => '.+\b\d{4}', // Sat Jan 27 2024
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();
        $h->hotel()->house();

        $status = $this->http->FindSingleNode("//*[ {$this->starts($this->t('Order'))} and {$this->contains($this->t('statusVariants'))} and following-sibling::*[normalize-space()][1][{$this->starts($this->t('confNumber'))}] ]", null, true, "/^{$this->opt($this->t('Order'))}\s+({$this->opt($this->t('statusVariants'))})$/i");

        $confirmation = $this->http->FindSingleNode("//*[ {$this->starts($this->t('confNumber'))} and preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Order'))}] ]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-Z\d]{5,})(?:\s*\(\s*(?<status>{$this->opt($this->t('statusVariants'))})\s*\))?$/", $confirmation, $m)) {
            // Reservation Number: 2-36980393    |    Reservation Number: 2-36980393 (Cancelled)
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));

            if (!empty($m['status'])) {
                $status = $m['status'];
            }
        }

        $h->general()->status($status);

        if (preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $status)) {
            $h->general()->cancelled();
        }

        $dateStart = strtotime($this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}$/")
            ?? $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['date']}$/"));
        $timeStart = $this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][3]", null, true, "/^{$patterns['time']}/")
            ?? $this->http->FindSingleNode("(//text()[{$this->eq($this->t('checkIn'))}])[last()]/following::text()[normalize-space()][2]", null, true, "/^{$patterns['time']}/");

        $dateEnd = strtotime($this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Check Out'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['date']}$/")
                ?? $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check Out'))}])[last()]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['date']}$/"));
        $timeEnd = $this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Check Out'))}] ]/*[normalize-space()][3]", null, true, "/^{$patterns['time']}/")

            ?? $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Check Out'))}])[last()]/following::text()[normalize-space()][2]", null, true, "/^{$patterns['time']}/");

        if ($dateStart && $timeStart) {
            $h->booked()->checkIn(strtotime($timeStart, $dateStart));
        }

        if ($dateEnd && $timeEnd) {
            $h->booked()->checkOut(strtotime($timeEnd, $dateEnd));
        }

        $siteNo = $this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Site #'))}] ]/*[normalize-space()][2]")
            ?? $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Site #'))}])[last()]/following::text()[normalize-space()][1]");
        $room = $h->addRoom();
        $room->setType($siteNo);

        $parkName = $this->http->FindSingleNode("//*[ *[normalize-space()][1][{$this->eq($this->t('Park'))}] ]/*[normalize-space()][2]")
            ?? $this->http->FindSingleNode("(//text()[normalize-space()='Park'])[1]/following::*[normalize-space()][1]");
        $h->hotel()->name($parkName ? 'Camping in park: ' . $parkName : null)->address($parkName);

        $traveller = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Primary Occupant:'))}]", null, true, "/^{$this->opt($this->t('Primary Occupant:'))}[:\s]*({$patterns['travellerName']})$/u");
        $h->general()->traveller($traveller, true);

        $guests = $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('Occupants:'))}]", null, true, "/^{$this->opt($this->t('Occupants:'))}[:\s]*(\d{1,3})$/");
        $h->booked()->guests($guests);

        if ($h->getCancelled()) {
            return $email;
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('ORDER TOTAL'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $134.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Use Fee'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and preceding-sibling::tr[*[1][{$this->eq($this->t('Use Fee'))}]] and following-sibling::tr[*[1][{$this->eq($this->t('SUBTOTAL'))}]] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $h->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
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
