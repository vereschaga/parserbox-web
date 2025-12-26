<?php

namespace AwardWallet\Engine\vueling\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class BeingMember extends \TAccountChecker
{
    public $mailFiles = "vueling/statements/it-63218588.eml, vueling/statements/it-63356279.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@comms.vueling.com') !== false
            || stripos($from, '@commercial.vueling.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".vueling.com/") or contains(@href,"comms.vueling.com") or contains(@href,"commercial.vueling.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@vueling.com")]')->length === 0
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

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode('*[normalize-space()][1]', $root, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $st->addProperty('Name', $name);

        $ffid = $this->http->FindSingleNode('*[normalize-space()][2]', $root, true, '/^[^:\d]+[:]+\s*([-A-Z\d\/]{5,})$/u');
        $st->setNumber($ffid);

        $st->setNoBalance(true);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $rule = '(starts-with(normalize-space(),"Account number") or starts-with(normalize-space(),"Nº de cuenta"))';
        // it-63356279.eml
        $nodes = $this->http->XPath->query("//*[ count(tr[not(.//tr) and normalize-space()])=2 and tr[normalize-space()][2][{$rule}] ]");

        if ($nodes->length === 0) {
            // it-63218588.eml
            $nodes = $this->http->XPath->query("//*[(self::td or self::th) and count(p[normalize-space()])=2 and p[normalize-space()][2][{$rule}] ]");
        }

        return $nodes;
    }
}
