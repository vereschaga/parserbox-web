<?php

namespace AwardWallet\Engine\goldpassport\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourAccount extends \TAccountChecker
{
    public $mailFiles = "goldpassport/statements/it-61256869.eml, goldpassport/statements/it-61476947.eml, goldpassport/statements/it-63095780.eml, goldpassport/statements/it-65090933.eml, goldpassport/statements/it-65154134.eml, goldpassport/statements/it-65098206.eml, goldpassport/statements/it-66387766.eml, goldpassport/statements/it-67410719.eml, goldpassport/statements/it-67499926.eml";

    public static $dictionary = [
        'zh' => [
            'DetectBody' => [
                '當前積分',
                '，您已成功註冊活動',
                '積分結餘',
                '網上管理訂閱狀態',
            ],
            'Account Balance:' => '積分結餘',
            'tierValues'       => ['會員', '探索者', '冒險家', '環球客'],
            //            'Hi' => '',
            'points' => '當前積分',
            'as of'  => '截至',
        ],
        'en' => [
            'DetectBody' => [
                'Account Balance:',
                ', you’re registered',
                'award in your account',
                'Member Experience',
                'Enter this registration code:', // it-67499926.eml
                'Manage your subscriptions online',
            ],
            'tierValues' => ['Member', 'Explorist', 'Discoverist', 'Globalist'],
            'points'     => ['Current Points', 'Points'],
            'Hi'         => ['Hi', 'Dear'],
        ],
    ];

    public $lang = 'en';

    private $subjects = [
        'zh' => [
            '您的賬戶摘要',
        ],
        'en' => [
            'Your Recent Account Activity',
            'Your Account Has Been Updated',
            'Welcome to Your World of Hyatt Membership',
            'You Have an Award in Your Account',
            ', Reserve Now, Vacation Later',
            'Your Account Summary –',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]hyatt\.com/i', $from) > 0;
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
        if ($this->http->XPath->query("//text()[{$this->contains([
            "We're glad you've been able to take advantage of your World of Hyatt membership",
            "We recently received a request to update your World of Hyatt account information",
            "Here's a highlight of the program and your benefits as a World of Hyatt Member:",
            "This exclusive offer lets you boost your World of Hyatt status",
        ])}]")->length > 0
        ) {
            return true;
        }

        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".hyatt.com/") or contains(@href,"world.hyatt.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Sincerely, World of Hyatt Customer Service") or contains(.,"worldofhyatt.com") or contains(.,"worldofhyatt@hyatt.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() || $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]]',
            'number'        => '[A-Z\d]\d{8}[A-Z]{1}',
        ];

        $st = $email->add()->statement();

        $xpathTopLinks = "(contains(normalize-space(),'Visit hyatt.com') and contains(normalize-space(),'Customer Service') and contains(normalize-space(),'My Account'))";

        $name = $this->http->FindSingleNode("//div[not(.//tr) and {$xpathTopLinks}]/following::text()[{$this->contains($this->t(', you’re registered'))}][1]", null, true, "/^({$patterns['travellerName']})\s*{$this->opt($this->t(', you’re registered'))}/u");

        if (!$name) {
            $name = $this->http->FindSingleNode("//div[not(.//tr) and {$xpathTopLinks}]/following::text()[{$this->starts($this->t('Hi'))}][1]", null, true, "/^{$this->opt($this->t('Hi'))}\s+({$patterns['travellerName']})[,:;! ]*$/u");
        }

        if (!$name) {
            $name = $this->http->FindSingleNode("//tr[not(.//tr) and {$xpathTopLinks}]/following::text()[{$this->starts($this->t('Hi'))}][1]", null, true, "/^{$this->opt($this->t('Hi'))}\s+({$patterns['travellerName']})[,:;! ]*$/u");
        }

        if (!$name) {
            // it-67499926.eml
            $fontColors = ['#0072ce', '#0072CE'];
            $xpathFont = "{$this->contains($fontColors, '@color')} or {$this->contains($fontColors, '@style')}";
            $name = $this->http->FindSingleNode("//tr[not(.//tr) and {$xpathTopLinks}]/following::text()[normalize-space()][1][ ancestor::*[{$xpathFont}] ]", null, true, "/^({$patterns['travellerName']})[ ]*,$/u");
        }

        if (!$name && ($roots = $this->findRoot())->length === 1) {
            // it-65098206.eml
            $root = $roots->item(0);
            $bgColors = ['#f0f', '#F0F'];
            $xpathBg = "{$this->contains($bgColors, '@bgcolor')} or {$this->contains($bgColors, '@style')}";
            $headerHtml = $this->http->FindHTMLByXpath("*[position()>1][(ancestor-or-self::*[{$xpathBg}] or descendant-or-self::*[{$xpathBg}]) and normalize-space()][last()]", null, $root);
            $headerText = $this->htmlToText($headerHtml);

            if (preg_match("/^\s*({$patterns['travellerName']})(?:\s*\||\s+{$this->opt(array_merge((array) $this->t('tierValues'), (array) $this->t('tierValues', 'en')))}|$)/iu", $headerText, $m)) {
                $name = $m[1];
            }
        }
        $st->addProperty('Name', $name);

        if ($name) {
            $nameVariants = [$name, strtoupper($name), strtolower($name), ucfirst(strtolower($name))];

            $headerHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($nameVariants)}]/ancestor::div[1][not(.//tr)]");
            $headerText = $this->htmlToText($headerHtml);

            if (empty($headerText)) {
                $headerHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts($nameVariants)}]/ancestor::tr[1]");
                $headerText = $this->htmlToText($headerHtml);
            }
//            $this->logger->debug($headerText);

            if (preg_match("/{$this->opt($this->t('Account Balance:'))}\s*(?<number>\d[,.\'\d ]*)$/", $headerText, $m)
                || preg_match("/(?<number>\d[,.\'\d ]*)\s*{$this->opt($this->t('points'))}/iu", $headerText, $m)
            ) {
                // Account Balance: 11,681    |    15,000 points    |    12,210當前積分
                $st->setBalance($this->normalizeAmount($m['number']));
            } else {
                $st->setNoBalance(true);
            }

            if (preg_match("/\s+{$this->opt($this->t('as of'))}\s+(?<asOf>.{6,}?)(?:\s*{$this->opt($this->t('Account Balance:'))}|$)/u", $headerText, $m)) {
                // as of July 8, 2020    |    截至 2020 年 8 月 26日
                $st->parseBalanceDate($this->normalizeDate($m['asOf']));
            }

            if (preg_match("/\s+({$patterns['number']})\s*/", $headerText, $m)) {
                $st->setNumber($m[1])
                    ->setLogin($m[1]);
            }

            if (preg_match("/^\s*{$this->opt($nameVariants)}\s*[A-Z]?\s+(\w+)[ \s|]+{$patterns['number']}/", $headerText, $m)
                || preg_match("/^\s*{$this->opt($nameVariants)}\s*[A-Z]?\s+({$this->opt(array_merge((array) $this->t('tierValues'), (array) $this->t('tierValues', 'en')))})\b/u", $headerText, $m)
            ) {
                $st->addProperty('Tier', $m[1]);
            }
        }

        $email->setType('YourAccount' . ucfirst($this->lang));

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

    private function findRoot(): \DOMNodeList
    {
        $nodes = $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::img] and *[2][contains(.,'|')] ]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//div[ count(div)=2 and div[1][descendant::img] and div[2][contains(.,'|')] ]");
        }
        $xpathRightLink = "(normalize-space()='My Account' or normalize-space()='我的賬戶')";

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//tr[ not(.//tr) and descendant::a[normalize-space()][last()][{$xpathRightLink}] ]/following::tr[normalize-space()][position()<5][ *[2] ][1]");
        }

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("//div[ not(.//div or .//tr) and descendant::a[normalize-space()][last()][{$xpathRightLink}] ]/following::div[normalize-space()][position()<5][ *[2] ][1]");
        }

        return $nodes;
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // July 8, 2020
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日$/u', $text, $m)) {
            // 2020 年 8 月 26日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['DetectBody'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['DetectBody'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function t(string $phrase, $lang = null)
    {
        if ($lang === null) {
            $lang = $this->lang;
        }

        if (!isset(self::$dictionary, $lang) || empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
