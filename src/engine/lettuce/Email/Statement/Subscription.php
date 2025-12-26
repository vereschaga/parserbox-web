<?php

namespace AwardWallet\Engine\lettuce\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "lettuce/statements/it-75901880.eml, lettuce/statements/it-75898432.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@frequentdiners.fbmta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // if ($this->detectEmailFromProvider( $parser->getHeader('from') ) !== true
        //     && $this->http->XPath->query('//a[contains(@href,"//FrequentDiners.fbmta.com/")]')->length === 0
        //     && $this->http->XPath->query('//node()[contains(normalize-space(),"This email was sent by: Lettuce") or contains(normalize-space(),"Lettuce Entertain You ® Enterprises, Inc. All rights reserved") or contains(normalize-space(),"joining The Frequent Diner Club")]')->length === 0
        // )
        //     return false;

        return stripos($parser->getHeader('from'), 'frequentdiners@frequentdiners.fbmta.com') !== false
            && $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $rootText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]"));

        $login = null;

        if (preg_match("/This email was sent to:\s*(\S+@\S+)\b/i", $rootText, $m)) {
            $login = $m[1];
            $st->setLogin($login);
        }

        if ($login) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(),'This email was sent to:')]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]");
    }
}
