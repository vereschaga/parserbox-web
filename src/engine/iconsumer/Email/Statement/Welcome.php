<?php

namespace AwardWallet\Engine\iconsumer\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "iconsumer/statements/it-90066072.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@iconsumer.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) === true
            && strpos($headers['subject'], "You're in! Start earning shares now") !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".iconsumer.com/") or contains(@href,"www.iconsumer.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Unsubscribe or manage iConsumer email")]')->length === 0
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
        return $this->http->XPath->query("//*[normalize-space()='WELCOME TO iCONSUMER']")->length > 0;
    }
}
