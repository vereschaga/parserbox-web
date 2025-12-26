<?php

namespace AwardWallet\Engine\shangrila\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class GoldenCircle extends \TAccountChecker
{
    public $mailFiles = "shangrila/statements/it-76385863.eml, shangrila/statements/it-77602576.eml, shangrila/statements/it-77729665.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Membership Number'               => ['Membership Number', 'Membership No.'],
            'Points Available for Redemption' => [
                'Points Available for Redemption',
                'Points Available For Redemption',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@e.shangri-la.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'goldencircle.mkt@e.shangri-la.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"shangri-la.chtah.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Shangri-La International Hotel Management Limited. All Rights Reserved") or contains(.,"www.shangri-la.com") or contains(.,"@shangri-la.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $number = $tier = $name = null;

        $rootText = $this->http->FindSingleNode('.', $root);

        if (preg_match("/{$this->opt($this->t('Membership Number'))}[: ]*([-A-Z\d]{5,})$/m", $rootText, $m)) {
            $number = $m[1];
        }

        if (preg_match("/^([A-Z\d]+)([Xx]{4,})$/", $number, $matches)) {
            // 69008687XXXX
            $st
                ->setNumber($matches[1])->masked('right')
                ->setLogin($matches[1])->masked('right')
                // ->addProperty('Number', $matches[1] . $matches[2])
            ;
        } elseif (preg_match("/^[A-Z\d]*\d[A-Z\d]*$/", $number)) {
            // 690086873694
            $st
                ->setNumber($number)
                ->setLogin($number)
                // ->addProperty('Number', $number)
            ;
        }

        $tier = $this->http->FindSingleNode("following-sibling::tr[ *[1][starts-with(normalize-space(),'Membership Tier')] ]/*[2]", $root, true, "/^(?:Gold|Jade|Diamond)$/i");

        if ($tier) {
            $st->addProperty('CurrentTier', $tier);
        }

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]", null, true, "/Dear\s+({$patterns['travellerName']})(?:[ ]*[,;!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->starts($this->t('Points Available for Redemption'))}] ]/*[2]", null, true, '/^\d[,.\'\d ]*$/');

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
            $statementPeriod = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->starts($this->t('Statement Period'))}] ]/*[2]", null, true, '/^.*\d.*$/');
            $dates = preg_split('/\s+-\s+/', $statementPeriod);

            if (count($dates) === 2) {
                // 30/06/2019
                $st->parseBalanceDate(preg_replace('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', '$2/$1/$3', $dates[1]));
            }
        } elseif ($number || $tier || $name) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[not(.//tr) and {$this->starts($this->t('Membership Number'))}]");
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
