<?php

namespace AwardWallet\Engine\rei\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Notice extends \TAccountChecker
{
    public $mailFiles = "rei/statements/it-76632351.eml, rei/statements/it-76284442.eml, rei/statements/it-76216113.eml, rei/statements/it-76002763.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'memberNumber' => ['Your Member Number:', 'Member number:'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@notices.rei.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".rei.com/") or contains(@href,"notices.rei.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->starts($this->t('memberNumber'))} or {$this->contains($this->t(', we’ve reset your REI password.'))}]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpathHidden = "(contains(@style,'display:none') or contains(@style,'display: none'))";
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $number = $name = $login = null;

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('memberNumber'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/");

        if ($number === null) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('memberNumber'))}]", null, true, "/{$this->opt($this->t('memberNumber'))}[: ]*([A-Z\d]{5,})$/");
        }

        if ($number !== null) {
            $st->setNumber($number);
        }

        $name = $this->nameFilter($this->http->FindSingleNode("//text()[{$this->eq($this->t('Thanks for your order,'))}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['travellerName']})!$/u"));

        if (!$name) {
            $name = $this->nameFilter($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))} and not(ancestor::*[{$xpathHidden}])]", null, true, "/^{$this->opt($this->t('Hi '))}\s*({$patterns['travellerName']})[ ]*,/u"));
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//*[{$this->starts($this->t('Log in with this email address:'))}]", null, true, "/^{$this->opt($this->t('Log in with this email address:'))}\s*(\S+@\S+)$/");

        if (!$login) {
            $login = $this->http->FindSingleNode("//text()[{$this->eq($this->t('This email was sent to'))} and not(ancestor::*[{$xpathHidden}])]/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");
        }

        if ($login) {
            $st->setLogin($login);
        }

        if ($number !== null || $name || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function nameFilter(?string $s): ?string
    {
        if (stripos($s, 'Customer') !== false) { // REI Customer
            $s = null;
        }

        return $s;
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
}
