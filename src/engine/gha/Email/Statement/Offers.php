<?php

namespace AwardWallet\Engine\gha\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Offers extends \TAccountChecker
{
    public $mailFiles = "gha/statements/it-469281543.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]ghadiscovery\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".ghadiscovery.com/") or contains(@href,"email.ghadiscovery.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Global Hotel Alliance. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('Offers');
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("*[1]/descendant::*[count(tr)=3][1]/tr[1]", $root, true, "/^D\s*[$]\s*(\d[,.\'\d ]*)$/i");
        $st->setBalance($balance);

        $number = $this->http->FindSingleNode("*[1]/descendant::*[count(tr)=3][1]/tr[3]", $root, true, "/^[A-z\d]{5,}$/");
        $st->setNumber($number);

        $status = $this->http->FindSingleNode("*[2]/descendant::img[normalize-space(@src)]/@src", $root, true, "/\/tierbadge_(SILVER|RED|BLACK|TITANIUM|PLATINUM|GOLD)\.[A-z]{3,4}\b/i");
        $st->addProperty('Status', $status);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $xpathNumber = "translate(normalize-space(),'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ','')=''";

        return $this->http->XPath->query("//tr[ count(*)=2 and *[1]/descendant::*[ count(tr)=3 and tr[1][starts-with(normalize-space(),'D$')] and tr[2][normalize-space()=''] and tr[3][normalize-space() and {$xpathNumber}] ] and *[2][not(.//tr) and descendant::img and normalize-space()=''] ]");
    }
}
