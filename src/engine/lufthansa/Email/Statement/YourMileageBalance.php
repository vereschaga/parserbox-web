<?php

namespace AwardWallet\Engine\lufthansa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourMileageBalance extends \TAccountChecker
{
    public $mailFiles = "lufthansa/statements/it-96873289.eml, lufthansa/statements/it-96802251.eml";

    public $lang = '';

    public static $dictionary = [
        'de' => [ // it-96802251.eml
            'Award miles'             => ['Prämienmeilen'],
            'Status miles'            => ['Statusmeilen'],
            'Dear'                    => 'Sehr geehrter',
            'Your mileage balance on' => 'Ihr Meilenkonto am',
        ],
        'en' => [ // it-96873289.eml
            'Award miles'  => ['Award miles'],
            'Status miles' => ['Status miles'],
        ],
    ];

    private $subjects = [
        'de' => ['Ihr aktueller Meilenstand im'],
        'en' => ['Your current mileage balance in'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mailing.milesandmore.com') !== false;
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
            && $this->http->XPath->query('//a[contains(@href,".milesandmore.com/") or contains(@href,"mailing.milesandmore.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourMileageBalance' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $balance = $balanceDate = $statusMiles = null;

        $welcomeText = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1]", $root);

        if (preg_match("/^{$this->opt($this->t('Dear'))}\s*(?<name>{$patterns['travellerName']})\s*,\s*{$this->opt($this->t('Your mileage balance on'))}\s+(?<date>.*\d.*)$/u", $welcomeText, $m)) {
            /*
                Dear Mr Porterie, Your mileage balance on 15.06.2021
            */
            $name = $m['name'];
            $balanceDate = strtotime($m['date']);
            $st->addProperty('Name', $name)->setBalanceDate($balanceDate);
        }

        $balance = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Award miles'))}\s*(\d[,.\'\d ]*)$/");

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        }

        $statusMiles = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Status miles'))}\s*(\d[,.\'\d ]*)$/");

        if ($statusMiles !== null) {
            $st->addProperty('StatusMiles', $statusMiles);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Award miles'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Status miles'))}] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Award miles']) || empty($phrases['Status miles'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Award miles'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Status miles'])}]")->length > 0
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
