<?php

namespace AwardWallet\Engine\isay\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourSurvey extends \TAccountChecker
{
    public $mailFiles = "isay/statements/it-89034160.eml, isay/statements/it-89080064.eml, isay/statements/it-89109593.eml, isay/statements/it-89079333.eml, isay/statements/it-89356142.eml, isay/statements/it-89276395.eml, isay/statements/it-89278710.eml, isay/statements/it-89435891.eml, isay/statements/it-89521423.eml, isay/statements/it-89389664.eml, isay/statements/it-89567611.eml, isay/statements/it-89589112.eml, isay/statements/it-88973576.eml, isay/statements/it-89275418.eml, isay/statements/it-89197117.eml";

    public $lang = '';

    public static $dictionary = [
        'zh' => [ // it-89080064.eml
            'youHave' => '你有',
            // 'points' => '',
            // 'Hi' => '',
        ],
        'nl' => [ // it-89109593.eml, it-89275418.eml
            'youHave' => ['Je hebt', 'Jouw i-Say punten'],
            'points'  => 'punten', // it-89567611.eml
            'Hi'      => 'Hallo',
        ],
        'no' => [ // it-89356142.eml, it-89276395.eml
            'youHave' => ['Du har', 'i-Say-poengene dine'],
            'points'  => 'poeng',
            'Hi'      => 'Hei',
        ],
        'fr' => [ // it-89278710.eml, it-88973576.eml
            'youHave' => ['Vous avez', 'Vos points i-Say'],
            // 'points' => '',
            'Hi' => 'Bonjour',
        ],
        'ms' => [ // it-89521423.eml
            'youHave' => 'Anda mempunyai',
            // 'points' => '',
            'Hi' => 'Hai',
        ],
        'en' => [ // it-89034160.eml, it-89435891.eml, it-89389664.eml
            'youHave' => 'You have',
            'points'  => 'points',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]i-say\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".i-say.com/") or contains(@href,"www.i-say.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1 || $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('YourSurvey' . ucfirst($this->lang));

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $balance = $name = null;

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);
            $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));

            /*
                You have:*
                1890
            */
            $pattern1 = "/^\s*{$this->opt($this->t('youHave'))}[:* ]*\s+[ ]*(\d[,.\'\d ]*)$/";

            /*
                1890 points*
            */
            $pattern2 = "/^\s*(\d[,.\'\d ]*)\s*{$this->opt($this->t('points'))}/";

            /*
                1890
            */
            $pattern3 = "/^\s*(\d[,.\'\d ]*)\s*$/"; // it-89589112.eml

            if (preg_match($pattern1, $rootText, $m) || preg_match($pattern2, $rootText, $m) || preg_match($pattern3, $rootText, $m)) {
                $balance = $this->normalizeAmount($m[1]);
            }
        }

        $patterns['hi'] = "/^{$this->opt($this->t('Hi'))}[ ]+({$patterns['travellerName']})(?:[ ]*[,;:!?]|$)/u";

        $name = $this->http->FindSingleNode("//h2[{$this->starts($this->t('Hi'))}]", null, true, $patterns['hi']);

        if (!$name) {
            // it-89276395.eml
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hi'))}]/following::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] ]", null, true, "/^{$patterns['travellerName']}$/u");
        }

        if (!$name) {
            // it-89435891.eml
            $nameTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, $patterns['hi']));

            if (count(array_unique($nameTexts)) === 1) {
                $name = array_shift($nameTexts);
            }
        }

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($name) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $nodes = $this->http->XPath->query("//tr/*[ not(.//tr) and count(descendant::text()[normalize-space()])>1 and descendant::text()[normalize-space()][1][{$this->starts($this->t('youHave'))} and contains(.,':')] ]");

        if ($nodes->length !== 1) {
            // it-89389664.eml
            $nodes = $this->http->XPath->query("//tr/*[ not(.//tr) and count(descendant::text()[normalize-space()])>1 and descendant::text()[normalize-space()][last()][{$this->starts($this->t('points'))}] ]");
        }

        if ($nodes->length !== 1) {
            // it-89276395.eml
            $nodes = $this->http->XPath->query("//span[ count(descendant::text()[normalize-space()])>1 and descendant::text()[normalize-space()][1][{$this->starts($this->t('youHave'))}] ]");
        }

        if ($nodes->length !== 1) {
            // it-89589112.eml
            $nodes = $this->http->XPath->query("//tr/*[ not(.//tr) and count(descendant::text()[normalize-space()])=1 and descendant::img[normalize-space(@alt)='Points'] ]");
        }

        return $nodes;
    }

    private function isMembership(): bool
    {
        $phrases = [
            'You are receiving this email to confirm your registration to join the i-Say', // it-89079333.eml
            'You are receiving this email as a registered member of the i-Say',
            'You are receiving this email because you are a member of i-Say',
            "You're an i-Say Member!", // it-89197117.eml
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0
            || $this->http->XPath->query("//h2[{$this->eq(['Your i-Say stats', 'Uw i-Say statistieken'])}]")->length > 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['youHave']) && $this->http->XPath->query("//*[{$this->contains($phrases['youHave'])}]")->length > 0
                || !empty($phrases['points']) && $this->http->XPath->query("//*[{$this->contains($phrases['points'])}]")->length > 0
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
