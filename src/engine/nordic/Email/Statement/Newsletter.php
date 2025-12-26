<?php

namespace AwardWallet\Engine\nordic\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Newsletter extends \TAccountChecker
{
    public $mailFiles = "nordic/statements/it-63159202.eml, nordic/statements/it-63158646.eml, nordic/statements/it-63158346.eml, nordic/statements/it-63161940.eml, nordic/statements/it-70568219.eml";

    public $lang = '';

    public static $dictionary = [
        'no' => [
            'linkText'         => ['Book hotell'],
            'membershipNumber' => 'Medl.nr',
            'membershipLevel'  => ['Medlemsnivå'],
            'points'           => ['Poeng for bruk', 'Poeng saldo'],
            'Hi'               => 'Hei',
        ],
        'sv' => [
            'linkText'         => ['Boka hotell'],
            'membershipNumber' => 'Medl.nr',
            'membershipLevel'  => ['Medlemsnivå'],
            'points'           => ['Poäng att använda'],
            //            'Hi' => '',
        ],
        'en' => [
            'linkText'         => ['Book hotel'],
            'membershipNumber' => 'Choice Club no',
            'membershipLevel'  => ['Membership level'],
            'points'           => ['Bonus points to use'],
            //            'Hi' => '',
        ],
    ];

    private $subjects = [
        'no' => ['Nå får du middag og overnatting fra'],
        'sv' => ['Middag och övernattning från'],
        'en' => ['Treat yourself to dinner and an overnight stay'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'newsletter@choice.no') !== false || stripos($from, 'newsletter@email.choice.no') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//nordicchoicehotelsdialog.com")]')->length === 0
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
        $email->setType('Newsletter' . ucfirst($this->lang));

        $xpathRow = '(self::tr or self::div)';

        $xpath = "//*[ *[{$xpathRow} and {$this->starts($this->t('membershipNumber'))}]/following-sibling::*[{$xpathRow} and {$this->starts($this->t('membershipLevel'))}] ]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            // it-70568219.eml
            $xpath = "//*[ *[{$xpathRow} and {$this->starts($this->t('membershipNumber'))}]/following-sibling::*[{$xpathRow} and {$this->starts($this->t('points'))}] ]";
            $roots = $this->http->XPath->query($xpath);
        }

        if ($roots->length === 0) {
            $this->logger->debug('Roots not found by XPath: ' . $xpath);

            return $email;
        }
        $this->logger->debug('Roots found success by XPath: ' . $xpath);
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $name = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1][not(descendant::a[normalize-space()])]", $root, true, "/^{$patterns['travellerName']}$/u");

        if (!$name) {
            // it-70568219.eml
            $name = $this->http->FindSingleNode("ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding-sibling::table[normalize-space()][2][not(descendant::a[normalize-space()])]", $root, true, "/^{$this->opt($this->t('Hi'))}\s*({$patterns['travellerName']})$/u");
        }
        $st->addProperty('Name', $name);

        $membershipNumber = $this->http->FindSingleNode("*[{$xpathRow}]/descendant::text()[{$this->starts($this->t('membershipNumber'))}]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');
        $st->setNumber($membershipNumber)
            ->setLogin($membershipNumber);

        $membershipLevel = $this->http->FindSingleNode("*[{$xpathRow}]/descendant::text()[{$this->starts($this->t('membershipLevel'))}]/following::text()[normalize-space()][1]", $root, true, '/^[^:]{2,}$/');

        if (!$membershipLevel) {
            // it-70568219.eml
            $membershipLevel = $this->http->FindSingleNode("ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding-sibling::table[normalize-space()][1][not(descendant::a[normalize-space()])]", $root, true, "/^(\w+)(?:\s+{$this->opt($this->t('member'))})?$/i");
        }
        $st->addProperty('Status', $membershipLevel);

        $points = $this->http->FindSingleNode("ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::text()[{$this->starts($this->t('points'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d[,.\'\d ]*$/');

        if ($points === null) {
            // it-70568219.eml
            $points = $this->http->FindSingleNode("*[{$xpathRow}]/descendant::text()[{$this->starts($this->t('points'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d[,.\'\d ]*$/');
        }

        if ($points !== null) {
            $st->setBalance($this->normalizeAmount($points));
        } elseif ($this->http->XPath->query("//*[{$xpathRow} and {$this->starts($this->t('points'))}]")->length === 0) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['linkText']) || empty($phrases['membershipLevel']) || empty($phrases['points'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['linkText'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['membershipLevel'])} or {$this->contains($phrases['points'])}]")->length > 0
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
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
