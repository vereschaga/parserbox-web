<?php

namespace AwardWallet\Engine\thaiair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountInformation extends \TAccountChecker
{
    public $mailFiles = "thaiair/statements/it-70514823.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thaiairways.com') !== false || stripos($from, '@royal-orchid-plus.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".thaiairways.com/") or contains(@href,"thaiairways.mail.txm32.net")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"ขอแสดงความนับถือ รอยัล ออร์คิด พลัส") or contains(.,"www.thaiairways.com") or contains(.,"@thaiairways.com")]')->length === 0
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

        $name = $this->http->FindSingleNode("tr[normalize-space()][1]", $root, true, "/^Dear\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/iu");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("tr[normalize-space()][2]", $root, true, "/^MEMBERSHIP NO[:\s]+([-A-Z\d]{5,})$/i");
        $st->setNumber($number)
            ->setLogin($number);

        if ($name || $number !== null) {
            $st->setNoBalance(true);
        }

        $email->setType('AccountInformation');

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ tr[normalize-space()][1][starts-with(normalize-space(),'Dear')] and tr[normalize-space()][2][starts-with(normalize-space(),'MEMBERSHIP NO')] ]");
    }
}
