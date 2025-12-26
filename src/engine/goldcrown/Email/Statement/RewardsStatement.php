<?php

namespace AwardWallet\Engine\goldcrown\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RewardsStatement extends \TAccountChecker
{
    public $mailFiles = "goldcrown/statements/it-63344182.eml, goldcrown/statements/it-72080868.eml, goldcrown/statements/it-93245565.eml";
    private $lang = '';
    private $reFrom = ['@bestwestern.com', '@infomail.bestwestern.com', '@news.bestwestern.co.uk'];
    private $reProvider = ['Best Western'];
    private $reSubject = [
        '| Best Western – ',
        'welcome to Best Western Rewards',
        'Promociones Best Western Rewards - Nuevos Socios Comerciales',
    ];
    private $reBody = [
        'en' => [
            ['My Rewards points: ', 'Our Brand Collection'],
            ['Points Balance:', 'Member Since:'], // it-93245565.eml
        ],
        'es' => [
            ['Promociones Best Western Rewards', 'Número de Asociado'],
        ],
    ];
    private static $dictionary = [
        'en' => [ // it-63344182.eml, it-93245565.eml
            // 'hello' => '',
            'My Rewards points:' => ['My Rewards points:', 'Points Balance:'],
        ],
        'es' => [ // it-72080868.eml
            'hello'         => 'Buenas tardes:',
            'Member Number' => 'Número de Asociado:',
            // 'My Rewards points:' => '',
            'My Account' => 'Mi Cuenta',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $name = $number = $balance = null;

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('hello'))}]/following::span[1]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//tr[ *[4][{$this->starts($this->t('Member Number'))}] ]/*[2]", null, true, "/^{$patterns['travellerName']}$/u") // it-93245565.eml
        ;

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member Number'))}]/following::text()[normalize-space()][string-length()>10][1]");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('My Rewards points:'))}]", null, true, "/{$this->opt($this->t('My Rewards points:'))}\s*(\d[,.\'\d ]*)$/")
            ?? $this->http->FindSingleNode("//tr/*[not(.//tr) and {$this->starts($this->t('My Rewards points:'))}]", null, true, "/{$this->opt($this->t('My Rewards points:'))}\s*(\d[,.\'\d ]*)$/") // it-93245565.eml
        ;

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
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
        if ($this->http->XPath->query("//a[{$this->contains('My Account')}]")->length == 0
            && $this->http->XPath->query("//a[{$this->contains('Mi Cuenta')}]")->length == 0
        ) {
            return false;
        }

        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
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
}
