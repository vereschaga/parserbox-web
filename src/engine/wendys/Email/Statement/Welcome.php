<?php

namespace AwardWallet\Engine\wendys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "wendys/statements/it-77849564.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.wendys.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'mywendys@email.wendys.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".wendys.com/") or contains(@href,"email.wendys.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@wendys.com") or contains(.,"@email.wendys.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($parser->getCleanFrom() === 'mywendys@email.wendys.com' || $this->isMembership()) {
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
        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'You got an account now')]")->length > 0;
    }
}
