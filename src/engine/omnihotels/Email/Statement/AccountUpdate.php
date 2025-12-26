<?php

namespace AwardWallet\Engine\omnihotels\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccountUpdate extends \TAccountChecker
{
    public $mailFiles = "omnihotels/statements/it-63496479.eml, omnihotels/statements/it-70972463.eml, omnihotels/statements/it-458975294.eml, omnihotels/statements/it-435661145.eml";
    public $lang = 'en';

    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $name = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            // it-63496479.eml
            $name = array_shift($travellerNames);
        } elseif (preg_match("/(?:^|\][: ]*)({$patterns['travellerName']})[ ]*,[ ]*Your(?: [[:alpha:]]+)? Select Guest(?: Member)? Account Summary/i", $parser->getSubject(), $m)) {
            // it-458975294.eml
            $name = $m[1];
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $level = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tier Level:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Tier Level:'))}\s*(\w+)$/");

        if (!empty($level)) {
            $st->addProperty('Level', $level);
        }

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Account Number:')]/ancestor::*[../self::tr][1]", null, true, "/{$this->opt($this->t('Account Number:'))}[:\s]*([A-Z\d]{5,})$/");

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Account Number:'))} ]")->count() === 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('#DC_CONTAINER70902#'))} ]")->count() > 0
        ) {
            // it-70972463.eml
        } else {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Eligible Tier Credits:')]/ancestor::tr[1]", null, true, "/Eligible Tier Credits:\s*(.+)$/") // it-63496479.eml
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'Eligible Tier')]/following::text()[normalize-space() and not(contains(normalize-space(),'Credits'))][1]") // it-435661145.eml
            ?? $this->http->FindSingleNode("//tr[normalize-space()='Credits Earned']/following-sibling::tr[normalize-space()]") // it-458975294.eml
        ;

        if ($balance === '#TIERNTSERNDCALYR#') {
            // it-70972463.eml
            $st->setNoBalance(true);
        } else {
            $st->setBalance($balance);
        }

        $nightsNextLevel = $this->http->FindSingleNode("//tr[normalize-space()='Nights To Next Level']/following-sibling::tr[normalize-space()]", null, true, "/^\d+$/");

        if ($nightsNextLevel !== null) {
            // it-458975294.eml
            $st->addProperty('NightsNextLevel', $nightsNextLevel);
        }

        if ($name === null && $level === null && $number === null && $nightsNextLevel === null
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Tier Credits'))}]")->count() > 0
        ) {
            // it-70972463.eml
            $st->setMembership(true);
        }

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent to')]/following::text()[contains(normalize-space(), '@')][1]");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:omnihotels-cme|omnihotels)\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Your August Account Summary') !== false
            || stripos($headers['subject'], 'Account Update + Expiring Soon:') !== false
            || preg_match('/Your [[:alpha:]]+ Statement/iu', $headers['subject']) > 0
            || preg_match('/[[:alpha:]][ ]*,[ ]*Your(?: [[:alpha:]]+)? Select Guest(?: Member)? Account Summary/iu', $headers['subject']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"Omni Hotels")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//text()[contains(normalize-space(),"Eligible Tier") or normalize-space()="Credits Earned"]')->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('#DC_CONTAINER70902#'))} ]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Tier Level'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($this->t('Account Number'))} ]")->length > 0
            );
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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
}
