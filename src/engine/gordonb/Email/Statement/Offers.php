<?php

namespace AwardWallet\Engine\gordonb\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Offers extends \TAccountChecker
{
    public $mailFiles = "gordonb/statements/it-82467759.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'gordonbiersch@craftworksrestaurants.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"you joined Gordon Biersch") or contains(normalize-space(),"you joined the Passport Rewards")]')->length === 0
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
        return $this->http->XPath->query("//*[contains(normalize-space(),'You are receiving this message because you joined Gordon Biersch Rewards') or contains(normalize-space(),'You are receiving this message because you joined the Passport Rewards') or contains(normalize-space(),'We received your request to reset your password for GB Rewards')]")->length > 0;
    }
}
