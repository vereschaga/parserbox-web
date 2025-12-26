<?php

namespace AwardWallet\Engine\oldchicago\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class OrderNow extends \TAccountChecker
{
    public $mailFiles = "oldchicago/statements/it-84608169.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@oldchicago.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".oldchicago.com/") or contains(@href,"//oldchicago.com") or contains(@stl_link_url,".oldchicago.com/") or contains(@stl_link_url,"//oldchicago.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//tr[not(.//tr) and starts-with(normalize-space(),'You are receiving this message because you are a member of OC Rewards or World Beer Tour at Old Chicago.')]")->length > 0;
    }
}
