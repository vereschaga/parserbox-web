<?php

namespace AwardWallet\Engine\scene\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Newsletter extends \TAccountChecker
{
    public $mailFiles = "scene/statements/it-85853500.eml, scene/statements/it-85034679.eml, scene/statements/it-85974804.eml, scene/statements/it-85850055.eml, scene/statements/it-85998573.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'viewOnline'        => ['Version en ligne'],
            'unsubscribe'       => ['Mettre à jour vos préférences de courriel ou vous désabonner'],
            'membershipPhrases' => ['Vous recevez ce courriel parce que vous êtes membre du programme SCÈNE'],
            'youHave'           => ['vous avez'],
            'youNumber'         => ['votre numéro', 'votre carte'],
        ],
        'en' => [
            'viewOnline'        => ['View online', 'View Online', 'Online version', 'Online Version'],
            'unsubscribe'       => ['Update your Email Preferences or Unsubscribe'],
            'membershipPhrases' => ['You are receiving this email because you are a member of the SCENE program'],
            'youHave'           => ['You have', 'you have'],
            // 'youNumber' => '',
        ],
    ];

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@news.scene.ca') !== false || stripos($from, '@news.sceneplus.ca') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".scene.ca/") or contains(@href,"news.scene.ca") or contains(@href,".sceneplus.ca/") or contains(@href,"news.sceneplus.ca")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"SCENE IP LP. All rights reserved") or contains(normalize-space(),"Scene IP LP. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && ($this->findRoot()->length === 1 || $this->isMembership());
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            if ($this->isMembership()) {
                $st->setMembership(true);
            }

            return $email;
        }
        $this->logger->debug('Statement format: ' . $this->format);
        $root = $roots->item(0);

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
            'cardNumber'    => '(?:604646|[Xx]{3,})[ \d]{2,}', // 604646 8697028865    |    xxxxxxxxxxxx8865
        ];

        $name = $balance = $number = null;

        $rootText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $root));
        $this->logger->debug($rootText);

        if (preg_match("/^\s*(?<name>{$patterns['travellerName']})[ ]*,[ ]*{$this->opt($this->t('youHave'))}[ ]*\n+.*?(?<amount>\d[,.\'\d ]*).*{$this->opt($this->t('points'))}/iu", $rootText, $m)) {
            /*
                Philip, you have
                2,778 SCENE points
            */
            $name = $m['name'];
            $balance = $m['amount'];
        } elseif (preg_match("/^\s*{$this->opt($this->t('youHave'))}\s+(?<amount>\d[,.\'\d ]*)\s*{$this->opt($this->t('points'))}/i", $rootText, $m)) {
            /*
                You have 1,263 points
            */
            $balance = $m['amount'];
        }

        if ($this->format === 3) {
            $balance = $this->http->FindSingleNode("tr[1]", $root, true, "/^(\d[,.\'\d ]*)\s*{$this->opt($this->t('points'))}/i");
            $number = $this->http->FindSingleNode("tr[3]", $root, true, "/^{$patterns['cardNumber']}$/");
        }

        // it-85974804.eml
        $numbers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('youNumber'))}]/following::text()[normalize-space()][1]", null, "/^{$patterns['cardNumber']}$/"));

        if (count(array_unique($numbers)) === 1) {
            $number = str_replace(' ', '', array_shift($numbers));
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if (preg_match("/^[Xx]+([ \d]+)$/", $number, $m)) {
            $m[1] = str_replace(' ', '', $m[1]);
            $st->setNumber($m[1])->masked()->setLogin($m[1])->masked();
        } elseif ($number) {
            $st->setNumber($number)->setLogin($number);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name || $number || $this->isMembership() === true) {
            $st->setNoBalance(true);
        }

        $email->setType('Newsletter' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        // it-85850055.eml
        $this->format = 1;
        $nodes = $this->http->XPath->query("//tr[count(*)=4 and count(*[normalize-space()=''])=3]/*[3][{$this->contains($this->t('points'))}]");

        if ($nodes->length !== 1) {
            // it-85034679.eml
            $this->format = 2;
            $nodes = $this->http->XPath->query("//tr[ count(*)=5 and count(*[normalize-space()=''])=3 and *[4][{$this->eq($this->t('viewOnline'))}] ]/*[2][{$this->contains($this->t('points'))}]");
        }

        if ($nodes->length !== 1) {
            // it-85998573.eml
            $this->format = 3;
            $nodes = $this->http->XPath->query("//*/tr[1][normalize-space()='' and descendant::img[contains(normalize-space(@alt),'SCENE card')] ]/following-sibling::tr[normalize-space()][1]/descendant::*[ count(tr)=3 and tr[1][{$this->contains($this->t('points'))}] ]");
        }

        return $nodes;
    }

    private function isMembership(): bool
    {
        // it-85853500.eml
        return $this->http->XPath->query("//*[{$this->contains($this->t('membershipPhrases'))}]")->length > 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['viewOnline']) || empty($phrases['unsubscribe'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['viewOnline'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['unsubscribe'])}]")->length > 0
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
