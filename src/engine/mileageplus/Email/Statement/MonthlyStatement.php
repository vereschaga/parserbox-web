<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: Looks like "MonthlyHighlights", but I would not combine
class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-226364225.eml, mileageplus/statements/it-226405058.eml, mileageplus/statements/it-47189878.eml, mileageplus/statements/it-62116614.eml, mileageplus/statements/it-66268438.eml, mileageplus/statements/it-68820536.eml, mileageplus/statements/it-72976891.eml, mileageplus/statements/st-68435465.eml, mileageplus/statements/st-69090635.eml";
    private $lang = '';
    private $reFrom = ['@news.united.com', 'MileagePlus_Partner@united.com'];

    private $reSubject = [
        ' monthly statement: ',
        'Support our communities impacted by severe weather',
        'Earn miles on your everyday spending all around town',
    ];

    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $this->lang = 'en';
        $root = $roots->item(0);

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $rootHtml = $this->http->FindHTMLByXpath('.', null, $root);
        $rootText = $this->htmlToText($rootHtml);

        /*
            MileagePlus # XXXXX732
            Mileage balance 1,020,703
        */

        $number = $balance = null;

        if (preg_match("/^[ ]*MileagePlus[ ]*#[ ]*X{2,}(\d+)[ ]*$/im", $rootText, $m)) {
            $number = $m[1];
        }
        $st->setLogin($number)->masked('left');
        $st->setNumber($number)->masked('left');

        $balance = $this->normalizeAmount($this->http->FindSingleNode('//text()[contains(normalize-space(),"You have") and contains(normalize-space(),"award miles")]', null, true, '/You have\s+(\d[,.\'\d]*)\s+award miles/i'));

        if ($balance === null && preg_match("/^[ ]*Mileage balance[: ]+(\d[,.\'\d]*)[ ]*$/im", $rootText, $m)) {
            $balance = $this->normalizeAmount($m[1]);
        }

        if ($balance === null) {
            $balance = $this->normalizeAmount($this->http->FindSingleNode('//text()[contains(normalize-space(),"Miles that never expire:")]',
                null, true, '/Miles that never expire:\s*(\d[,.\'\d]*)\s*$/i'));

            if ($balance !== null) {
                $st->setBalanceDate(strtotime($this->http->FindSingleNode('//text()[starts-with(normalize-space(),"(As of")][preceding::td[1][contains(., "MileagePlus") and (contains(., "summary") or contains(., "Summary"))]]',
                    null, true, '/^\s*\(As of\s+(.+?)\s*\)\s*$/i')));
            }
        }

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('protect your family'))}]", null, false, "/^({$patterns['travellerName']})[ ]*,[ ]*{$this->opt($this->t('protect your family'))}/u");

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, false, "/^{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})(?:\s*[,;:?!]|$)/u");
        }

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'(As of')]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][starts-with(normalize-space(), 'MileagePlus')]]", null, false, "/^\s*({$patterns['travellerName']})\s*$/u");
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('MileagePlus status'))}]/..",
            null, false, "/{$this->opt($this->t('MileagePlus status'))}\s*:\s*([-®\w\s]{4,})$/");

        if ($status) {
            $st->addProperty('MemberStatus', $status);
        }

        $eliteFlights = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Premier qualifying flights'))}]/..", null, false, '/:\s+([\d.,\s]+)/');

        if ($eliteFlights === null) {
            $eliteFlights = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PQF'))}]/..",
                null, false, '/^\s*(\d[\d.,\s]*)\s*' . $this->opt($this->t('PQF')) . '/');
        }

        if ($eliteFlights !== null) {
            $st->addProperty('EliteFlights', str_replace(',', '', $eliteFlights));
        }

        $elitePoints = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Premier qualifying points'))}]/..", null, false, '/:\s+([\d.,\s]+)/');

        if ($elitePoints === null) {
            $elitePoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PQP'))}][following::text()[{$this->eq($this->t('PQF'))}]]/..",
                null, false, '/^\s*(\d[\d.,\s]*)\s*' . $this->opt($this->t('PQP')) . '\b/');
        }

        if ($elitePoints !== null) {
            $st->addProperty('ElitePoints', str_replace(',', '', $elitePoints));
        }

        $lifetimeMiles = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Lifetime flight miles'))}]/..", null, false, '/:\s+([\d.,\s]+)/');

        if ($lifetimeMiles !== null) {
            $st->addProperty('LifetimeMiles', str_replace(',', '', $lifetimeMiles));
        }

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($name
            || $elitePoints !== null && $lifetimeMiles !== null
            || $this->http->XPath->query("//*[{$this->contains(['miles', 'balance'])}]")->length === 0
        ) {
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
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'United Hotels') === false
        ) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'MileagePlus Statement')]/preceding::text()[normalize-space()][1][normalize-space()='From:']")->length == 0
            && $this->http->XPath->query('//a[contains(@href,".united.com/") or contains(@href,"news.united.com")]')->length == 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"United Airlines. All rights reserved") or contains(.,"dining.mileageplus.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $xpathHide = "ancestor-or-self::*[contains(@style,'display:none') or contains(normalize-space(@style),'display: none')]";

        return $this->http->XPath->query("//*[count(tr[normalize-space()])=1]/tr[count(*[normalize-space()])=1]/*[descendant::img]/following-sibling::*[ descendant::text()[contains(normalize-space(),'MileagePlus') and contains(normalize-space(),'# XXXX') and not({$xpathHide})] ]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
