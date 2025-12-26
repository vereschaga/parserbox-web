<?php

namespace AwardWallet\Engine\see\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourOrder3 extends \TAccountChecker
{
    public $mailFiles = "see/it-580940687.eml, see/it-648494365.eml, see/it-825471960.eml";
    public $lang = '';

    public static $dictionary = [
        'en' => [
            'orderDetails' => ['Order summary'],
            'confNumber'   => ['BOOKING REFERENCE', 'Order Number'],
            'dear'         => 'Hi',
            'guestTitle'   => ['Standing', 'Tier B', ' - ADULT', 'x Adult', 'x Young Person', 'x Admission', 'x Bilhete', 'x Seats'],
            ' at '         => [' at ', '- Start time:'],
            'removeRow'    => ['Doors open', 'Please arrive'],
        ],
    ];

    private $subjects = [
        'en' => [' - E-Ticket Order'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@seetickets.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".seetickets.com/") or contains(@href,"www.seetickets.com") or contains(@href,".seetickets.fr/") or contains(@href,"faq.seetickets.fr")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpathTime = 'contains(translate(.,"0123456789：","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';

        $patterns = [
            // Mr. Hao-Li Huang
            'travellerName' => '[[:alpha:]][-.\'’£[:alpha:] ]*[[:alpha:]]',
            // 02/12/2023  |  Saturday 11th January 2025  |  Saturday October 5th 2024
            'date' => '(?:\d{1,2}\/\d{1,2}\/\d{4}|[-[:alpha:]]+[,\s]+\d{1,2}(?:[a-z]{2})?[,\s]+[[:alpha:]]+[,\s]+\d{4}|[-[:alpha:]]+[,\s]+[[:alpha:]]+[,\s]+\d{1,2}(?:[a-z]{2})?[,\s]+\d{4})',
            // 4:19PM  |  2:00 p. m.
            'time' => '\d{1,2}[.:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        ];

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourOrder3' . ucfirst($this->lang));

        $text = str_replace("> ", "", $parser->getBody());

        $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('orderDetails'))}]/following::text()[normalize-space()][1][contains(normalize-space(), '-')]/following::text()[normalize-space()][not(contains(normalize-space(), 'Please arrive'))][1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('orderDetails'))}]/following::text()[normalize-space()][2][{$this->contains($this->t(' at '))}]/following::text()[normalize-space()][not(contains(normalize-space(), 'Please arrive'))][1]");
        }

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('guestTitle'))}]/preceding::text()[normalize-space()][not(contains(normalize-space(), 'Please arrive'))][2][{$this->contains($this->t(' at '))} or {$xpathTime}]/ancestor::th[1]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('orderDetails'))}]/following::text()[contains(normalize-space(), 'Adult/teen') or contains(normalize-space(), 'x Admission') or contains(normalize-space(), 'Family Entry') or contains(normalize-space(), 'Advance Family')]/preceding::text()[contains(normalize-space(), ':') or {$this->contains($this->t(' at '))} or {$xpathTime}][1]");
        }

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('orderDetails'))}]/following::text()[contains(normalize-space(),' at ') or {$xpathTime}][1]");
        }

        foreach ($nodes as $root) {
            $e = $email->add()->event();
            $e->type()->event();

            $traveller = null;
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('dear'))}]", null, "/^{$this->opt($this->t('dear'))}[,\s\-]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
            }
            $traveller = $this->normalizeTraveller($traveller);
            $e->general()->traveller($traveller);

            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

            if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-Z\d]{4,40})$/", $confirmation, $m)) {
                $e->general()->confirmation($m[2], $m[1]);
            }

            $eventInfo = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/descendant::text()[normalize-space()]", $root));

            $eventInfo = preg_replace("/({$this->opt($this->t('removeRow'))}.+\n)/u", '', $eventInfo);

            // Saturday 9 December 2023, 10:00 to 12:00 ENTRY, Entry Ticket
            if (preg_match("/^(?<date>.+?\d{4})[,\s]+(?<depTime>{$patterns['time']})\s+to\s+(?<endTime>{$patterns['time']})[, ]+(?<eName>.{2,})(?:\n|$)/iu", $eventInfo, $m)
                || preg_match("/^(?<date>.+?\d{4})[,\s]+(?<depTime>{$patterns['time']})[, ]+(?<eName>.{2,})(?:\n|$)/", $eventInfo, $m)
                // Saturday October 5th 2024 at 11:40am
                || preg_match("/^(?<date>{$patterns['date']})(?:\s*at\s*|[ ]+)(?<depTime>{$patterns['time']})$/iu", $eventInfo, $m)
                || preg_match("/^(?<eName>.{2,}?)\s+(?<date>{$patterns['date']})\s*-\s*Start time:\s*(?<depTime>{$patterns['time']})\s+(?<address>.{3,})$/u", $eventInfo, $m)
                || preg_match("/^(?<eName>.{2,}?)\s+(?<date>{$patterns['date']})(?:\s*at\s*|[ ]+)(?<depTime>{$patterns['time']})\n*(?<address>.{3,})$/u", $eventInfo, $m)
                // 02/12/2023 10:00 - 10/12/2023 16:00
                || preg_match("/^(?<eName>.{2,})\n(?<date>{$patterns['date']})[,\s]*(?<depTime>{$patterns['time']})\s*-\s*(?<endDate>{$patterns['date']})?[,\s]*(?<endTime>{$patterns['time']})\n+(?<address>.{3,})$/u", $eventInfo, $m)
            ) {
                if (empty($m['eName'])) {
                    $m['eName'] = '';
                }

                $eventName = preg_match('/^ENTRY, Entry Ticket/i', $m['eName']) ? '' : $m['eName'];

                if (stripos($m['depTime'], '.') !== false) {
                    $m['depTime'] = str_replace('.', '', $m['depTime']);
                }

                if (stripos($m['date'], 'th') !== false) {
                    $m['date'] = str_replace('th', '', $m['date']);
                }

                if (empty($eventName) && !empty($eventInfo)) {
                    $eventName = $this->http->FindSingleNode("//text()[{$this->eq($eventInfo)}]/preceding::text()[normalize-space()][1]");
                }

                if (empty($eventName)) {
                    $eventName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order confirmed:'))}]", null, true, "/{$this->opt($this->t('Order confirmed:'))}\s*(.+)/");
                }

                $e->setName($eventName);

                if (isset($m['address'])) {
                    $address = $m['address'];
                }

                if (!empty($address)) {
                    $e->setAddress($address);
                } else {
                    $address = $this->http->FindSingleNode("//text()[{$this->eq($eventInfo)}]/following::text()[normalize-space()][1]");

                    if (!empty($address)) {
                        $e->setAddress($address);
                    }
                }

                $e->setStartDate(strtotime($m['depTime'], strtotime($m['date'])));

                if (!empty($m['endDate']) && !empty($m['endTime'])) {
                    $e->setEndDate(strtotime($m['endTime'], strtotime($m['endDate'])));
                } elseif (!empty($m['endTime'])) {
                    $e->setEndDate(strtotime($m['endTime'], strtotime($m['date'])));
                } else {
                    $e->setNoEndDate(true);
                }

                //get text for current event

                $eNameVariants = array_unique(array_filter([$m['eName'], $eventName]));

                if (count($eNameVariants) > 0
                    && preg_match("/{$this->opt($eNameVariants)}[ ]*\n(?<guestInfo>[ ]*(?:\d{1,3}\s*x\s*.+\n[ ]*\D{1,3} ?\d[,.\d]*[ ]*\n+){1,3})/u", $text, $m)
                ) {
                    //get count Adults
                    if (preg_match_all("/^(\d+)\s*x\s*(?:Adult|Admission)/m", $m['guestInfo'], $match)) {
                        $e->booked()
                            ->guests(array_sum($match[1]));
                    }

                    //get count Child
                    if (preg_match_all("/^(\d+)\s*x\s*(?:Child)/m", $m['guestInfo'], $match)) {
                        $e->booked()
                            ->kids(array_sum($match[1]));
                    }
                }
            }

            if ($e->getGuestCount() == 0) {
                $guests = $this->http->FindSingleNode("//text()[{$this->eq($e->getName())}]/following::text()[{$this->contains($this->t('guestTitle'))}][1]", null, true, "/^(\d+)\s*x/");

                if (empty($guests)) {
                    $guests = $this->re("/^(\d+)\s*{$this->opt($this->t('guestTitle'))}/mu", $eventInfo);
                }

                if (!empty($guests)) {
                    $e->setGuestCount($guests);
                }
            }

            if ($e->getKidsCount() == 0) {
                $kids = $this->http->FindSingleNode("//text()[{$this->eq($e->getName())}]/following::text()[contains(normalize-space(), 'x Child')][1]", null, true, "/^(\d+)\s*x/");

                if (!empty($kids)) {
                    $e->setKidsCount($kids);
                }
            }

            $total = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[normalize-space()][last()]", $root, true, "/^(\D{1,3}[\d\.\,]+)$/");

            if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $total, $m)) {
                $e->price()
                    ->currency($this->normalizeCurrency($m['currency']))
                    ->total(PriceHelper::parse($m['total'], $m['currency']));
            }

            $emailTotal = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::tr[1]/descendant::text()[normalize-space()][last()]", $root, true, "/^(\D{1,3}[\d\.\,]+)$/");

            if (empty($emailTotal)) {
                $emailTotal = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[normalize-space()][last()]", $root, true, "/{$this->opt($this->t('Total'))}\s*(\D{1,3}[\d\.\,]+)/");
            }

            if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $emailTotal, $m)) {
                $email->price()
                    ->currency($this->normalizeCurrency($m['currency']))
                    ->total(PriceHelper::parse($m['total'], $m['currency']));
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['orderDetails']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['orderDetails'])}]")->length > 0
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
            'CAD' => ['CA$'],
            'AUD' => ['A$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
        ], [
            '$1',
        ], $s);
    }
}
