<?php

namespace AwardWallet\Engine\searsshop\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Advertisement extends \TAccountChecker
{
    public $mailFiles = "searsshop/statements/it-79972915.eml, searsshop/statements/it-80094283.eml";

    public $lang = 'en';

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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".shopyourway.com/") or contains(@href,"link.shopyourway.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"contact us at Shop Your Way") or contains(.,"www.shopyourway.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->starts($this->t('memberNo'))}]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(),'To set your new password, please click the button below')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $number = $name = null;

        // it-79972915.eml
        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('memberNo'))}]", null, true, "/{$this->opt($this->t('memberNo'))}[: ]+([-A-Z\d]{5,})$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('memberNo'))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
        }

        if ($number) {
            $st->setNumber($number);
        }

        // it-80094283.eml
        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hi')]", null, true, "/^Hi\s+({$patterns['travellerName']})(?:[ ]*[,:;!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($number || $name) {
            $st->setNoBalance(true);
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
