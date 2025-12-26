<?php

namespace AwardWallet\Engine\searsshop\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class StatementIsHere extends \TAccountChecker
{
    public $mailFiles = "searsshop/statements/it-228061825.eml, searsshop/statements/it-228133966.eml";

    public $lang = 'en';

    public $detectSubject = [
        'Statement is here!',
    ];
    public static $dictionary = [
        'en' => [
            'memberNo' => [
                'MEMBER #', 'MEMBER#',
                'Hi! Member #', 'Hi! Member#',
                'Hi, Valued Member! Member #', 'Hi, Valued Member! Member#',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]shopyourwayrewards\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'rewards@rewards.shopyourwayrewards.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".shopyourway.com/") or contains(@href,"link.shopyourway.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"contact us at Shop Your Way") or contains(.,"www.shopyourway.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->eq($this->t('YOUR MONTHLY STATEMENT'))}] | //img[{$this->eq($this->t('YOUR MONTHLY STATEMENT'), '@alt')} or {$this->eq($this->t('YOUR MONTHLY STATEMENT'), '@title')}]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Member #'))}])[1]", null, true, "/{$this->opt($this->t('Member #'))}[: ]+([\d]{5,})\s*$/");
        $st->setNumber($number);
        if (!empty($number)) {
            $st->setNoBalance(true);
        }

        if ($this->detectEmailFromProvider($parser->getHeader('from')) === true
            && preg_match("/^\s*([[:alpha:]]+),\s*your [[:alpha:]]+ Statement is here/", $parser->getSubject(), $m)
        ) {
            $st->addProperty('Name', $m[1]);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
